<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\LocationStatus;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PelunasanController;
use App\Http\Controllers\API\StatusApproval;
use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrProspect;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use App\Models\M_CustomerExtra;
use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_FirstArr;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LocationStatus;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Models\M_TelegramBotSend;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

use function Symfony\Component\Mailer\Event\getMessage;

class WelcomeOLDD extends Controller
{
    protected $locationStatus;

    public function __construct(LocationStatus $locationStatus)
    {
        $this->locationStatus = $locationStatus;
    }

    public function index(Request $request)
    {
        try {
            return response()->json("OK");
        } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        $uid = Uuid::uuid7()->toString();

        $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid);

        if (isset($res['diskon_denda']) && $res['diskon_denda'] == 1) {
            $this->updateDiscountArrears($request, $loan_number, $tgl_angsuran, $res, $uid);
        } else {
            $this->updateArrears($request, $loan_number, $tgl_angsuran, $res, $uid);
        }

        if ($res['bayar_angsuran'] != 0 || $res['bayar_denda'] != 0 || (isset($res['diskon_denda']) && $res['diskon_denda'] == 1)) {
            $this->createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch, $uid);
        }

        $this->updateCredit($loan_number);
    }

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->orderBy('PAYMENT_DATE', 'ASC')->first();

        if (!$credit_schedule) {
            throw new Exception("Credit Schedule Not Found", 404);
        }

        $byr_angsuran = $res['bayar_angsuran'];
        $flag = $res['flag'];

        if ($credit_schedule || $byr_angsuran != 0 || $flag != 'PAID') {

            $payment_value = $byr_angsuran + $credit_schedule->PAYMENT_VALUE;

            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;

                $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;

                $valInterest = $new_payment_value_interest - $valBeforeInterest;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
            }

            $total_paid = $new_payment_value_principal + $new_payment_value_interest;

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                ? ($total_paid - $credit_schedule->INSTALLMENT)
                : 0;

            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;
            $updates['PAYMENT_VALUE'] = $payment_value;

            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            $credit_schedule->update(['PAID_FLAG' => $credit_schedule->PAYMENT_VALUE >= $credit_schedule->INSTALLMENT ? 'PAID' : '']);
        }
    }

    private function updateCredit($loan_number)
    {

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if (!$check_credit) {
            throw new Exception("Credit Not Found", 404);
        }

        $checkCreditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->get();

        $checkArrears = M_Arrears::where('LOAN_NUMBER', $loan_number)
            ->whereIn('STATUS_REC', ['A', 'PENDING'])
            ->get();

        if ($checkCreditSchedule->isEmpty() && $checkArrears->isEmpty()) {
            $status = 'D';
            $status_rec = 'CL';
        } else {
            $status = 'A';
        }

        if ($check_credit) {
            $check_credit->update([
                'STATUS' => $status,
                'STATUS_REC' => $status_rec ?? 'AC',
            ]);
        }
    }

    private function updateDiscountArrears($request, $loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->orderBy('START_DATE', 'ASC')->first();

        $byr_angsuran = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        if ($check_arrears) {
            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $paymentData = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($paymentData);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $remainingPenalty = floatval($getPenalty) - floatval($bayar_denda);
            if ($remainingPenalty > 0) {
                $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $remainingPenalty);
                M_PaymentDetail::create($discountPaymentData);
                $this->addCreditPaid($loan_number, ['DISKON_DENDA' => $remainingPenalty]);
            }

            $updates['PAID_PENALTY'] = $getPenalty;
            $updates['END_DATE'] = $request['created_at'];
            $updates['UPDATED_AT'] = $request['created_at'];
            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $check_arrears->update(['STATUS_REC' => $remainingPenalty > 0 ? 'D' : 'S']);
        }
    }

    private function updateArrears($request, $loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->orderBy('START_DATE', 'ASC')->first();

        $byr_angsuran = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        if ($check_arrears || $res['bayar_denda'] != 0) {
            $current_penalty = $check_arrears->PAID_PENALTY;

            $new_penalty = $current_penalty + $bayar_denda;

            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($data);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $updates['PAID_PENALTY'] = $new_penalty;
            $updates['END_DATE'] = $request['created_at'];
            $updates['TRNS_CODE'] = 'BOTEX';
            $updates['UPDATED_AT'] = $request['created_at'];
            $updates['STATUS_REC'] = 'A';

            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $total1 = round(floatval($new_payment_value_principal) + floatval($new_payment_value_interest) + floatval($new_penalty), 2);
            $total2 = round(floatval($getPrincipal) + floatval($getInterest) + floatval($getPenalty), 2);

            if ($total1 == $total2 || (floatval($new_penalty) > floatval($getPenalty))) {
                $check_arrears->update(['STATUS_REC' => 'S']);
            }
        }
    }

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $uid)
    {
        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => $res['flag'] == 'PAID' ? 'angsuran_denda' : $request['payment_type'] ?? '',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'BRANCH' => $branch->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => $request['created_at'],
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran,
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at'],
            'ARREARS_ID' => $res['id_arrear'] ?? '',
            'BANK_NAME' => round(microtime(true) * 1000)
        ]);
    }

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    public function addCreditPaid($loan_number, array $data)
    {
        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($check_credit) {
            $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK'] : 0;
            $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] : 0;
            $paidPenalty = isset($data['BAYAR_DENDA']) ? $data['BAYAR_DENDA'] : 0;

            $check_credit->update([
                'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
                'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
                'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty)
            ]);
        }
    }

    function proccess($request, $loan_number, $no_inv, $status)
    {
        $pelunasanKwitansiDetail = M_KwitansiDetailPelunasan::where(['no_invoice' => $no_inv, 'loan_number' => $loan_number])->get();

        $this->proccessPinaltyPayment($request, $no_inv, $status, $loan_number);

        if (!empty($pelunasanKwitansiDetail)) {
            foreach ($pelunasanKwitansiDetail as $res) {
                $uid = Uuid::uuid7()->toString();
                $this->proccessPayment($request, $uid, $no_inv, $status, $res);

                $paymentDetails = [
                    'BAYAR_POKOK' => $res['bayar_pokok'] ?? 0,
                    'BAYAR_BUNGA' => $res['bayar_bunga'] ?? 0,
                    'BAYAR_DENDA' => $res['bayar_denda'] ?? 0,
                    'DISKON_POKOK' => $res['diskon_pokok'] ?? 0,
                    'DISKON_BUNGA' => $res['diskon_bunga'] ?? 0,
                    'DISKON_DENDA' => $res['diskon_denda'] ?? 0,
                ];

                foreach ($paymentDetails as $type => $amount) {
                    if ($amount != 0) {
                        $this->proccessPaymentDetail($uid, $type, $amount);
                    }
                }

                if ($res['installment'] != 0) {
                    $this->updateCreditSchedulePelunasan($loan_number, $res);
                }

                $this->updateArrearsPelunasan($loan_number, $res);
                $this->updateCreditPelunasan($res, $loan_number);
            }
        }
    }

    function proccessPaymentDetail($payment_id, $acc_key, $amount)
    {
        M_PaymentDetail::create([
            'ID' => Uuid::uuid7()->toString(),
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ]);
    }

    function proccessPayment($request, $uid, $no_inv, $status, $res)
    {
        $originalAmount = (
            ($res['bayar_pokok'] ?? 0) +
            ($res['bayar_bunga'] ?? 0) +
            ($res['bayar_denda'] ?? 0) +
            ($res['diskon_pokok'] ?? 0) +
            ($res['diskon_bunga'] ?? 0) +
            ($res['diskon_denda'] ?? 0));

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Pelunasan Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::findOrFail($request['cabang'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $res['loan_number'] ?? '',
            'ENTRY_DATE' => $request['created_at'],
            'TITLE' => 'Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'ORIGINAL_AMOUNT' => $originalAmount,
            'START_DATE' => $res['tgl_angsuran'] ?? '',
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at']
        ]);
    }

    function proccessPinaltyPayment($request, $no_inv, $status, $loan_number)
    {
        $uid = Uuid::uuid7()->toString();

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Bayar Pelunasan Pinalty',
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::findOrFail($request['cabang'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number ?? '',
            'ENTRY_DATE' => $request['created_at'],
            'TITLE' => 'Bayar Pelunasan Pinalty',
            'ORIGINAL_AMOUNT' => $request['bayar_pinalty'] ?? 0,
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at']
        ]);

        if ($request['bayar_pinalty'] != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN PINALTY', $request['bayar_pinalty'] ?? 0);
        }

        if ($request['diskon_pinalty'] != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN DISKON PINALTY', $request['diskon_pinalty'] ?? 0);
        }
    }

    function updateCreditSchedulePelunasan($loan_number, $res)
    {

        $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])->first();

        if ($getCreditSchedule) {

            $valBeforePrincipal = $getCreditSchedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $getCreditSchedule->PAYMENT_VALUE_INTEREST;
            $valBeforeDiscPrincipal = $getCreditSchedule->DISCOUNT_PRINCIPAL;
            $valBeforeDiscInterest = $getCreditSchedule->DISCOUNT_INTEREST;

            $ttlPrincipal = floatval($valBeforePrincipal) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($valBeforeInterest) + floatval($res['bayar_bunga'] ?? 0);
            $ttlDiscPrincipal = floatval($valBeforeDiscPrincipal) + floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($valBeforeDiscInterest) + floatval($res['diskon_bunga'] ?? 0);

            $getCreditSchedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $ttlPrincipal,
                'PAYMENT_VALUE_INTEREST' => $ttlInterest,
                'DISCOUNT_PRINCIPAL' => $ttlDiscPrincipal,
                'DISCOUNT_INTEREST' => $ttlDiscInterest,
                'PAID_FLAG' => 'PAID'
            ]);

            $ttlPayment = $ttlPrincipal + $ttlInterest + $ttlDiscPrincipal + $ttlDiscInterest;

            $getCreditSchedule->update([
                'PAYMENT_VALUE' => $ttlPayment
            ]);
        }
    }

    function updateArrearsPelunasan($loan_number, $res)
    {
        $getArrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $res['tgl_angsuran'],
        ])->orderBy('START_DATE', 'ASC')->first();

        if ($getArrears) {
            $ttlPrincipal = floatval($getArrears->PAID_PCPL) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($getArrears->PAID_INT) + floatval($res['bayar_bunga'] ?? 0);
            $ttlPenalty = floatval($getArrears->PAID_PENALTY) + floatval($res['bayar_denda'] ?? 0);
            $ttlDiscPrincipal = floatval($getArrears->WOFF_PCPL) + floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($getArrears->WOFF_INT) + floatval($res['diskon_bunga'] ?? 0);
            $ttlDiscPenalty = floatval($getArrears->WOFF_PENALTY) + floatval($res['diskon_denda'] ?? 0);

            $getArrears->update([
                'END_DATE' => Carbon::now('Asia/Jakarta')->format('Y-m-d') ?? null,
                'PAID_PCPL' => $ttlPrincipal ?? 0,
                'PAID_INT' => $ttlInterest ?? 0,
                'PAID_PENALTY' => $ttlPenalty ?? 0,
                'WOFF_PCPL' => $ttlDiscPrincipal ?? 0,
                'WOFF_INT' => $ttlDiscInterest ?? 0,
                'WOFF_PENALTY' => $ttlDiscPenalty ?? 0,
                'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
            ]);

            $checkDiscountArrears = ($ttlDiscPrincipal + $ttlDiscInterest + $ttlDiscPenalty) == 0;

            $getArrears->update([
                'STATUS_REC' => $checkDiscountArrears ? 'S' : 'D',
            ]);
        }
    }

    function updateCreditPelunasan($res, $loan_number)
    {
        $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

        if ($credit) {
            $credit->update([
                'PAID_PRINCIPAL' => floatval($credit->PAID_PRINCIPAL) + floatval($res['bayar_pokok']),
                'PAID_INTEREST' => floatval($credit->PAID_INTEREST) + floatval($res['bayar_bunga']),
                'DISCOUNT_PRINCIPAL' => floatval($credit->DISCOUNT_PRINCIPAL) + floatval($res['diskon_pokok']),
                'DISCOUNT_INTEREST' => floatval($credit->DISCOUNT_INTEREST) + floatval($res['diskon_bunga']),
                'PAID_PENALTY' => floatval($credit->PAID_PENALTY) + floatval($res['bayar_denda']),
                'DISCOUNT_PENALTY' => floatval($credit->DISCOUNT_PENALTY) + floatval($res['diskon_denda']),
                'STATUS' => 'D',
                'STATUS_REC' => 'PT',
                'END_DATE' => now()
            ]);
        }
    }
}

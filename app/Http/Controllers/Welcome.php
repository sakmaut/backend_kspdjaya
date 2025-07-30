<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\AdminFeeController;
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
use App\Models\M_InterestDecreasesSetting;
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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;

use function Symfony\Component\Mailer\Event\getMessage;

class Welcome extends Controller
{
    public function index(Request $request)
    {
        $data = M_CrApplication::where('ORDER_NUMBER', $request->order_number)->first();

        $schedule = $this->generateAmortizationScheduleBungaMenurun($request->set_date, $data);

        // $db = M_InterestDecreasesSetting::first();

        // $plafond = intval($request->plafond ?? 0);
        // $interest = ($db->interest ?? 0) / 100;
        // $tenor = $db->tenor;
        // $admin_fee = $db->admin_fee;

        // $formula = floatval(($plafond + $admin_fee) * ($tenor / 12) * $interest);

        // $schedule = [];
        // for ($i = 1; $i <= $tenor; $i++) {
        //     $schedule[] = [
        //         'angsuran_ke' => $i,
        //         'tgl_angsuran' => $this->setDate(),
        //         'pokok' => ($i === $tenor) ? $plafond : 0,
        //         'bunga' => $formula,
        //         'total_angsuran' => (($i === $tenor) ? $plafond : 0) + $formula,
        //         'baki_debet' => $plafond - (($i === $tenor) ? $plafond : 0)
        //     ];
        // }

        return response()->json($schedule);


        // $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID', 'f20ac08b-3f9c-47ed-9d69-7d248a74cad9')->where(function ($query) {
        //     $query->whereNull('DELETED_AT')
        //         ->orWhere('DELETED_AT', '');
        // })->get();

        // return response()->json($data_collateral);
        // die;

        // $getPenalty = 35200.01;
        // $new_penalty = 35200.00;
        // $setArrears = bccomp($getPenalty, $new_penalty, 2) === 0 ? 'S' : 'A';
        // return response()->json($setArrears);
        // die;

        // $currentDate = '2025-04-26';
        // // $currentDate = now();
        // $date = Carbon::parse($currentDate);

        // $day = $date->day;

        // if ($day >= 26 && $day <= 31) {
        //     $newDay = $day - 24;
        //     $date->addMonthsNoOverflow(1)->day = $newDay;
        // }

        // $setDate = $date->format('Y-m-d');
        // // $endDate = Carbon::parse($setDate)->addMonths(intval(6))->format('Y-m-d');

        // return response()->json($setDate);
        // die;

        // if ($request->bearerToken()) {
        //     // Find the token using the provided bearer token
        //     $token = PersonalAccessToken::findToken($request->bearerToken());

        //     if (!$token) {
        //         return response()->json([
        //             'token' => false,
        //             'message' => 'Token not found or invalid'
        //         ], 401);
        //     }

        //     $dateToken =  date('Ymd', strtotime($token->expires_at));
        //     $dateNow =  date('Ymd', strtotime(now()));

        //     if ($dateToken != $dateNow || $token->expires_at == null || !$token->expires_at || empty($token->expires_at)) {
        //         return response()->json([
        //             'token' => false,
        //             'message' => 'Token expired'
        //         ], 401);
        //     }

        //     return response()->json([
        //         'token' => true
        //     ], 200);
        // }

        // // If no bearer token is provided
        // return response()->json([
        //     'token' => false,
        //     'message' => 'Authorization token missing'
        // ], 401);
    }

    public function indexs(Request $req)
    {
        DB::beginTransaction();
        try {
            return response()->json("asdasdasd");
            die;
            $inv = $req->no_invoice;

            $queryAngsuran = "  SELECT  a.NO_TRANSAKSI,
                                a.LOAN_NUMBER,
                                a.PAYMENT_TYPE,
                                a.METODE_PEMBAYARAN,
                                a.BRANCH_CODE,
                                a.TGL_TRANSAKSI,
                                a.CREATED_BY,
                                a.CREATED_AT,
                                a.PINALTY_PELUNASAN,
                                a.DISKON_PINALTY_PELUNASAN,
                                b.*
                    FROM kwitansi a
                        LEFT JOIN kwitansi_structur_detail b
                        ON b.no_invoice = a.NO_TRANSAKSI
                    WHERE a.STTS_PAYMENT = 'PAID'
                        AND a.NO_TRANSAKSI = '$inv'
                        AND a.PAYMENT_TYPE = 'angsuran'
                        AND (b.installment != 0 OR b.bayar_angsuran != 0 OR b.bayar_denda != 0 OR b.diskon_denda != 0)
        			ORDER BY b.tgl_angsuran ASC";

            $resultsAngsuran = DB::select($queryAngsuran);

            if (empty($resultsAngsuran)) {
                throw new Exception("Invoice Not Found", 404);
            }

            $structuredDataAngsuran = [];

            foreach ($resultsAngsuran as $result) {

                if (!isset($structuredDataAngsuran[$result->NO_TRANSAKSI])) {
                    $structuredDataAngsuran[$result->NO_TRANSAKSI] = [
                        'payment_type' => $result->PAYMENT_TYPE,
                        'payment_method' => $result->METODE_PEMBAYARAN,
                        'no_transaksi' => $result->NO_TRANSAKSI,
                        'no_fasilitas' => $result->LOAN_NUMBER,
                        'bayar_pinalty' => $result->PINALTY_PELUNASAN,
                        'diskon_pinalty' => $result->DISKON_PINALTY_PELUNASAN,
                        'cabang' =>  $result->BRANCH_CODE,
                        'created_by' => $result->CREATED_BY,
                        'created_at' => $result->CREATED_AT,
                        'struktur' => [],
                    ];
                }

                $structuredDataAngsuran[$result->NO_TRANSAKSI]['struktur'][] = [
                    'id' => $result->id,
                    'no_invoice' => $result->NO_TRANSAKSI,
                    'key' => $result->key,
                    'angsuran_ke' => $result->angsuran_ke,
                    'loan_number' => $result->LOAN_NUMBER,
                    'tgl_angsuran' => $result->tgl_angsuran,
                    'principal' => $result->principal,
                    'interest' => $result->interest,
                    'installment' => $result->installment,
                    'principal_remains' => $result->principal_remains,
                    'payment' => $result->payment,
                    'bayar_angsuran' => $result->bayar_angsuran,
                    'bayar_denda' => $result->bayar_denda,
                    'total_bayar' => $result->total_bayar,
                    'flag' => $result->flag,
                    'denda' => $result->denda,
                    'diskon_denda' => $result->diskon_denda,
                ];
            }

            $arrearsData = [];
            foreach ($structuredDataAngsuran as $request) {
                $struktur = $request['struktur'];

                if (isset($struktur) && is_array($struktur)) {
                    foreach ($struktur as $res) {

                        $paymntDate = date('Y-m-d', strtotime($res['tgl_angsuran']));

                        $getCrditSchedule = "   SELECT LOAN_NUMBER,PAYMENT_DATE,PRINCIPAL,INTEREST,INSTALLMENT,PAYMENT_VALUE_PRINCIPAL,PAYMENT_VALUE_INTEREST
                                                    FROM credit_schedule
                                                    WHERE PAYMENT_DATE = '$paymntDate'
                                                        AND LOAN_NUMBER = '{$res['loan_number']}'
                                                        AND (PAID_FLAG IS NULL OR PAID_FLAG = '')
                                                    ORDER BY PAYMENT_DATE ASC";


                        $updateArrears = DB::select($getCrditSchedule);

                        foreach ($updateArrears as $list) {
                            $date = date('Y-m-d', strtotime($request['created_at']));
                            $daysDiff = (strtotime($date) - strtotime($list->PAYMENT_DATE)) / (60 * 60 * 24);
                            $pastDuePenalty = $list->INSTALLMENT * ($daysDiff * 0.005);

                            if ($pastDuePenalty <= 0) {
                                $pastDuePenalty = 0;
                            }

                            $arrearsData[] = [
                                'ID' => Uuid::uuid7()->toString(),
                                'STATUS_REC' => 'A',
                                'LOAN_NUMBER' => $list->LOAN_NUMBER,
                                'START_DATE' => $list->PAYMENT_DATE,
                                'END_DATE' => null,
                                'PAST_DUE_PCPL' => $list->PRINCIPAL ?? 0,
                                'PAST_DUE_INTRST' => $list->INTEREST ?? 0,
                                'PAST_DUE_PENALTY' => $pastDuePenalty ?? 0,
                                'PAID_PCPL' => $list->PAYMENT_VALUE_PRINCIPAL ?? 0,
                                'PAID_INT' => $list->PAYMENT_VALUE_INTEREST ?? 0,
                                'PAID_PENALTY' => 0,
                                'KWITANSI_DATE' => $date,
                                'CREATED_AT' => Carbon::now('Asia/Jakarta')
                            ];
                        }
                    }
                }
            }

            foreach ($arrearsData as $data) {
                $existingArrears = M_Arrears::where([
                    'LOAN_NUMBER' => $data['LOAN_NUMBER'],
                    'START_DATE' => $data['START_DATE'],
                    'STATUS_REC' => 'A'
                ])
                    ->orderBy('START_DATE', 'ASC')
                    ->first();

                if ($existingArrears) {
                    $existingArrears->update([
                        'PAST_DUE_PENALTY' => $data['PAST_DUE_PENALTY'] ?? 0,
                        'UPDATED_AT' => Carbon::now('Asia/Jakarta')
                    ]);
                } else {
                    $getNow = $data['KWITANSI_DATE'];

                    if ($data['START_DATE'] < $getNow) {
                        M_Arrears::create($data);
                    }
                }
            }

            foreach ($structuredDataAngsuran as $request) {
                $no_inv = $request['no_transaksi'];
                $getCodeBranch = M_Branch::findOrFail($request['cabang']);
                $struktur = $request['struktur'];

                if (isset($struktur) && is_array($struktur)) {
                    foreach ($struktur as $res) {

                        M_KwitansiStructurDetail::firstOrCreate([
                            'no_invoice' => $no_inv,
                            'key' => $res['key'] ?? '',
                            'loan_number' => $res['loan_number'] ?? ''
                        ], [
                            'angsuran_ke' => $res['angsuran_ke'] ?? '',
                            'tgl_angsuran' => $res['tgl_angsuran'] ?? '',
                            'principal' => $res['principal'] ?? '',
                            'interest' => $res['interest'] ?? '',
                            'installment' => $res['installment'] ?? '',
                            'principal_remains' => $res['principal_remains'] ?? '',
                            'payment' => $res['payment'] ?? '',
                            'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
                            'bayar_denda' => $res['bayar_denda'] ?? '',
                            'total_bayar' => $res['total_bayar'] ?? '',
                            'flag' => $res['flag'] ?? '',
                            'denda' => $res['denda'] ?? '',
                            'diskon_denda' => $res['diskon_denda']
                        ]);

                        if ($res['bayar_angsuran'] != 0) {
                            $cekPaid = M_CreditSchedule::where([
                                'LOAN_NUMBER' => $res['loan_number'],
                                'PAYMENT_DATE' => Carbon::parse($res['tgl_angsuran'])->format('Y-m-d'),
                                'PAID_FLAG' => 'PAID'
                            ])->first();

                            if ($cekPaid) {
                                throw new Exception("Credit Schedule Sudah PAID", 500);
                            }
                        }

                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv);
                    }
                }
            }

            DB::commit();
            return response()->json("MUACHHHHHHHHHHHHHH");
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
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
                'END_DATE' => Carbon::now()->format('Y-m-d') ?? null,
                'PAID_PCPL' => $ttlPrincipal ?? 0,
                'PAID_INT' => $ttlInterest ?? 0,
                'PAID_PENALTY' => $ttlPenalty ?? 0,
                'WOFF_PCPL' => $ttlDiscPrincipal ?? 0,
                'WOFF_INT' => $ttlDiscInterest ?? 0,
                'WOFF_PENALTY' => $ttlDiscPenalty ?? 0,
                'UPDATED_AT' => Carbon::now(),
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

    private function generateAmortizationScheduleBungaMenurun($setDate, $data)
    {
        $schedule = [];
        $ttal_bayar = ($data->SUBMISSION_VALUE + $data->TOTAL_ADMIN);
        $angsuran_bunga = $data->INSTALLMENT;
        $term = ceil($data->TENOR);
        $baki_debet = $ttal_bayar;

        for ($i = 1; $i <= $term; $i++) {
            $pokok = 0;

            if ($i == $term) {
                $pokok = $ttal_bayar;
            }

            $total_angsuran = $pokok + $angsuran_bunga;

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => setPaymentDate($setDate, $i),
                'baki_debet_awal' => floatval($baki_debet),
                'pokok' => floatval($pokok),
                'bunga' => floatval($angsuran_bunga),
                'total_angsuran' => floatval($total_angsuran),
                'baki_debet' => floatval($baki_debet - $pokok)
            ];

            $baki_debet -= $pokok;
        }

        return $schedule;
    }

    private function generateAmortizationSchedule($setDate, $data)
    {
        $schedule = [];
        $remainingBalance = $data->POKOK_PEMBAYARAN;
        $term = ceil($data->TENOR);
        $angsuran = $data->INSTALLMENT;
        $suku_bunga_konversi = ($data->FLAT_RATE / 100);
        $ttal_bunga = $data->TOTAL_INTEREST;
        $totalInterestPaid = 0;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($remainingBalance * $suku_bunga_konversi, 2);

            if ($i < $term) {
                $principalPayment = round($angsuran - $interest, 2);
            } else {
                $principalPayment = round($remainingBalance, 2);
                $interest = round($ttal_bunga - $totalInterestPaid, 2);
            }

            $totalPayment = round($principalPayment + $interest, 2);
            $remainingBalance = round($remainingBalance - $principalPayment, 2);
            $totalInterestPaid += $interest;
            if ($i == $term) {
                $remainingBalance = 0.00;
            }

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => setPaymentDate($setDate, $i),
                'baki_debet_awal' => floatval($remainingBalance + $principalPayment),
                'pokok' => floatval($principalPayment),
                'bunga' => floatval($interest),
                'total_angsuran' => floatval($totalPayment),
                'baki_debet' => floatval($remainingBalance)
            ];
        }

        return $schedule;
    }

    function add($data)
    {
        // $schedule = $this->generateAmortizationSchedule($request->set_date, $data);

        // $loan = DB::table('credit')
        //     ->where('ORDER_NUMBER', $request->order_number)
        //     ->select('LOAN_NUMBER')
        //     ->first();

        // if ($loan) {
        //     foreach ($schedule as $value) {
        //         DB::table('credit_schedule')
        //             ->where('LOAN_NUMBER', $loan->LOAN_NUMBER)
        //             ->where('INSTALLMENT_COUNT', $value['angsuran_ke'])
        //             ->update([
        //                 'PRINCIPAL'     => $value['pokok'],
        //                 'INTEREST'   => $value['bunga'],
        //                 'INSTALLMENT'   => $value['total_angsuran'],
        //                 'PRINCIPAL_REMAINS' => $value['baki_debet'],
        //                 'PAYMENT_VALUE_PRINCIPAL' => $value['pokok'],
        //                 'PAYMENT_VALUE_INTEREST' => $value['bunga']
        //             ]);
        //     }
        // }
    }
}

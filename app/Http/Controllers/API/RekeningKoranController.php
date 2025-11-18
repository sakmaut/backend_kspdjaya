<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\TasksLogging\TasksRepository;
use App\Http\Resources\R_KwitansiPelunasan;
use App\Http\Resources\R_Pelunasan;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CreditTransaction;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class RekeningKoranController extends Controller
{

    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function processPayment(Request $request)
    {
        DB::beginTransaction();
        try {

            $check = $request->only(['BAYAR_POKOK', 'BAYAR_BUNGA', 'BAYAR_DENDA']);

            if (array_sum($check) == 0) {
                throw new Exception('Null Kabeh');
            }

            $loan_number = $request->LOAN_NUMBER;

            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            if (!M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->exists()) {
                $this->saveKwitansi($request, $detail_customer, $no_inv);
            }

            $data = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            $dto = new R_KwitansiPelunasan($data);

            DB::commit();
            return response()->json($dto, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
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
                    $this->updateCreditSchedule($loan_number, $res);
                }

                $this->updateArrears($loan_number, $res);
                $this->updateCredit($res, $loan_number);
            }
        }
    }

    function proccessCancel($loan_number, $no_inv, $status)
    {
        try {
            $pelunasanKwitansiDetail = M_KwitansiDetailPelunasan::where(['no_invoice' => $no_inv, 'loan_number' => $loan_number])->get();

            $kwitansi = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            if ($kwitansi) {
                $kwitansi->update(['STTS_PAYMENT' => $status]);
            }

            $getCredit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

            if ($getCredit) {
                $getCredit->update([
                    "PINALTY_PELUNASAN" => floatval($getCredit->PINALTY_PELUNASAN ?? 0) - floatval($kwitansi->PINALTY_PELUNASAN ?? 0),
                    "DISKON_PINALTY_PELUNASAN" => floatval($getCredit->DISKON_PINALTY_PELUNASAN ?? 0) - floatval($kwitansi->DISKON_PINALTY_PELUNASAN ?? 0),
                ]);
            }

            $payment = M_Payment::where('INVOICE', $no_inv)->get();

            if (!empty($payment)) {
                foreach ($payment as $list) {
                    $list->update(['STTS_PAYMENT' => $status]);
                }
            }

            if (!empty($pelunasanKwitansiDetail)) {
                foreach ($pelunasanKwitansiDetail as $res) {

                    if ($res['installment'] != 0) {
                        $this->cancelCreditSchedule($loan_number, $res);
                    }

                    if ($res['bayar_denda'] != 0 || $res['diskon_denda'] != 0) {
                        $this->cancelArrears($loan_number, $res);
                    }

                    $this->cancelCredit($loan_number, $res);
                }
            }
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
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

        $kwitansi = M_Kwitansi::where(['NO_TRANSAKSI' => $no_inv])->first();

        if ($kwitansi) {
            $user_id = $kwitansi->CREATED_BY;
            $getCodeBranch = M_Branch::find($kwitansi->BRANCH_CODE);
        }

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Pelunasan Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? $request->payment_method,
            'INVOICE' => $no_inv,
            'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? M_Branch::find($request->user()->branch_id)->CODE_NUMBER,
            'LOAN_NUM' => $res['loan_number'] ?? '',
            'ENTRY_DATE' => Carbon::now(),
            'TITLE' => 'Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'ORIGINAL_AMOUNT' => $originalAmount,
            'START_DATE' => $res['tgl_angsuran'] ?? '',
            'END_DATE' => Carbon::now(),
            'USER_ID' => $user_id ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => Carbon::now()
        ]);
    }

    function proccessPinaltyPayment($request, $no_inv, $status, $loan_number)
    {
        $uid = Uuid::uuid7()->toString();

        $kwitansi = M_Kwitansi::where(['NO_TRANSAKSI' => $no_inv])->first();

        if ($kwitansi) {
            $user_id = $kwitansi->CREATED_BY;
            $getCodeBranch = M_Branch::find($kwitansi->BRANCH_CODE);

            M_Payment::create([
                'ID' => $uid,
                'ACC_KEY' => 'Bayar Pelunasan Pinalty',
                'STTS_RCRD' => $status,
                'NO_TRX' => $no_inv,
                'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? $request->payment_method,
                'INVOICE' => $no_inv,
                'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? M_Branch::find($request->user()->branch_id)->CODE_NUMBER,
                'LOAN_NUM' => $loan_number ?? '',
                'ENTRY_DATE' => Carbon::now(),
                'TITLE' => 'Bayar Pelunasan Pinalty',
                'ORIGINAL_AMOUNT' => $kwitansi->PINALTY_PELUNASAN ?? 0,
                'END_DATE' => Carbon::now(),
                'USER_ID' => $user_id ?? $request->user()->id,
                'AUTH_BY' => $request->user()->fullname ?? '',
                'AUTH_DATE' => Carbon::now()
            ]);

            if ($kwitansi->PINALTY_PELUNASAN != 0) {
                $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN PINALTY', $kwitansi->PINALTY_PELUNASAN ?? 0);
            }

            if ($kwitansi->DISKON_PINALTY_PELUNASAN != 0) {
                $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN DISKON PINALTY', $kwitansi->DISKON_PINALTY_PELUNASAN ?? 0);
            }
        }
    }

    function updateCreditSchedule($loan_number, $res)
    {

        $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])
            ->orderBy('PAYMENT_DATE', 'ASC')
            ->first();

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
            $insufficientPay = $getCreditSchedule->INSTALLMENT - $ttlPayment;

            $getCreditSchedule->update([
                'INSUFFICIENT_PAYMENT' => $insufficientPay == 0 ? 0 : $insufficientPay,
                'PAYMENT_VALUE' => $ttlPayment
            ]);
        }
    }

    function cancelCreditSchedule($loan_number, $res)
    {
        $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])->orderBy('PAYMENT_DATE', 'ASC')->first();

        if ($getCreditSchedule) {

            $valBeforePrincipal = $getCreditSchedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $getCreditSchedule->PAYMENT_VALUE_INTEREST;
            $valBeforeDiscPrincipal = $getCreditSchedule->DISCOUNT_PRINCIPAL;
            $valBeforeDiscInterest = $getCreditSchedule->DISCOUNT_INTEREST;
            $valPaymentValue = $getCreditSchedule->PAYMENT_VALUE;

            $ttlPrincipal = floatval($valBeforePrincipal) - floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($valBeforeInterest) - floatval($res['bayar_bunga'] ?? 0);
            $ttlDiscPrincipal = floatval($valBeforeDiscPrincipal) - floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($valBeforeDiscInterest) - floatval($res['diskon_bunga'] ?? 0);
            $ttlPayment = $valPaymentValue - (floatval($res['bayar_pokok'] ?? 0) + floatval($res['bayar_bunga'] ?? 0) + floatval($res['diskon_pokok'] ?? 0) + floatval($res['diskon_bunga'] ?? 0));

            $ttlPrincipal = max($ttlPrincipal, 0);
            $ttlInterest = max($ttlInterest, 0);
            $ttlDiscPrincipal = max($ttlDiscPrincipal, 0);
            $ttlDiscInterest = max($ttlDiscInterest, 0);
            $ttlPayment = max($ttlPayment, 0);

            $getCreditSchedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $ttlPrincipal,
                'PAYMENT_VALUE_INTEREST' => $ttlInterest,
                'DISCOUNT_PRINCIPAL' => $ttlDiscPrincipal,
                'DISCOUNT_INTEREST' => $ttlDiscInterest,
                'PAYMENT_VALUE' => $ttlPayment,
                'PAID_FLAG' => ''
            ]);
        }
    }

    function updateArrears($loan_number, $res)
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

    function cancelArrears($loan_number, $res)
    {
        $getArrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $res['tgl_angsuran'],
        ])->orderBy('START_DATE', 'ASC')->first();

        if ($getArrears) {
            $ttlPrincipal = floatval($getArrears->PAID_PCPL) - floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($getArrears->PAID_INT) - floatval($res['bayar_bunga'] ?? 0);
            $ttlPenalty = floatval($getArrears->PAID_PENALTY) - floatval($res['bayar_denda'] ?? 0);
            $ttlDiscPrincipal = floatval($getArrears->WOFF_PCPL) - floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($getArrears->WOFF_INT) - floatval($res['diskon_bunga'] ?? 0);
            $ttlDiscPenalty = floatval($getArrears->WOFF_PENALTY) - floatval($res['diskon_denda'] ?? 0);

            $ttlPrincipal = max($ttlPrincipal, 0);
            $ttlInterest = max($ttlInterest, 0);
            $ttlPenalty = max($ttlPenalty, 0);
            $ttlDiscPrincipal = max($ttlDiscPrincipal, 0);
            $ttlDiscInterest = max($ttlDiscInterest, 0);
            $ttlDiscPenalty = max($ttlDiscPenalty, 0);

            $getArrears->update([
                'END_DATE' => Carbon::now()->format('Y-m-d'),
                'PAID_PCPL' => $ttlPrincipal,
                'PAID_INT' => $ttlInterest,
                'PAID_PENALTY' => $ttlPenalty,
                'WOFF_PCPL' => $ttlDiscPrincipal,
                'WOFF_INT' => $ttlDiscInterest,
                'WOFF_PENALTY' => $ttlDiscPenalty,
                'STATUS_REC' => 'A',
                'UPDATED_AT' => Carbon::now(),
            ]);
        }
    }

    function updateCredit($res, $loan_number)
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

    function cancelCredit($loan_number, $res)
    {
        $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

        if ($credit) {
            $credit->update([
                'PAID_PRINCIPAL' => max(floatval($credit->PAID_PRINCIPAL) - floatval($res['bayar_pokok'] ?? 0), 0),
                'PAID_INTEREST' => max(floatval($credit->PAID_INTEREST) - floatval($res['bayar_bunga'] ?? 0), 0),
                'DISCOUNT_PRINCIPAL' => max(floatval($credit->DISCOUNT_PRINCIPAL) - floatval($res['diskon_pokok'] ?? 0), 0),
                'DISCOUNT_INTEREST' => max(floatval($credit->DISCOUNT_INTEREST) - floatval($res['diskon_bunga'] ?? 0), 0),
                'PAID_PENALTY' => max(floatval($credit->PAID_PENALTY) - floatval($res['bayar_denda'] ?? 0), 0),
                'DISCOUNT_PENALTY' => max(floatval($credit->DISCOUNT_PENALTY) - floatval($res['diskon_denda'] ?? 0), 0),
                'END_DATE' => now(),
                'STATUS' => 'A'
            ]);
        }
    }

    private function saveKwitansi($request, $customer, $no_inv)
    {
        $checkKwitansiExist = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if ($checkKwitansiExist) {
            throw new Exception("Kwitansi Exist", 500);
        }

        $idGenerate = Uuid::uuid7()->toString();

        $checkPaymentMethod = strtolower($request->METODE_PEMBAYARAN) == 'cash';

        $data = [
            "PAYMENT_TYPE" => 'pelunasan_rekening_koran',
            "PAYMENT_ID" => $idGenerate ?? '',
            "STTS_PAYMENT" => $checkPaymentMethod ? 'PAID' : 'PENDING',
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->LOAN_NUMBER,
            "TGL_TRANSAKSI" => Carbon::now(),
            "CUST_CODE" => $customer->CUST_CODE,
            "BRANCH_CODE" => $request->user()->branch_id,
            "NAMA" => $customer->NAME,
            "ALAMAT" => $customer->ADDRESS,
            "RT" => $customer->RT,
            "RW" => $customer->RW,
            "PROVINSI" => $customer->PROVINCE,
            "KOTA" => $customer->CITY,
            "KECAMATAN" => $customer->KECAMATAN,
            "KELURAHAN" => $customer->KELURAHAN,
            "METODE_PEMBAYARAN" => $request->METODE_PEMBAYARAN,
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR ?? 0,
            "PINALTY_PELUNASAN" => $request->BAYAR_PINALTI ?? 0,
            "DISKON_PINALTY_PELUNASAN" => $request->DISKON_PINALTI ?? 0,
            "PEMBULATAN" => $request->PEMBULATAN,
            "DISKON" => $request->JUMLAH_DISKON,
            "KEMBALIAN" => $request->KEMBALIAN,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->id
        ];

        M_Kwitansi::create($data);

        $credit = M_Credit::where('LOAN_NUMBER', $request->LOAN_NUMBER)->first();

        $bayarPokok = $request->BAYAR_POKOK ?? 0;
        $bayarBunga = $request->BAYAR_BUNGA ?? 0;
        $bayarDenda = $request->BAYAR_DENDA ?? 0;

        if ($credit && $checkPaymentMethod) {
            $oldMaxPrincipal = $credit->MAX_PRINCIPAL ?? 0;
            $oldPaidPrincipal = $credit->PAID_PRINCIPAL ?? 0;
            $oldPaidInterest = $credit->PAID_INTEREST ?? 0;
            $oldPaidPinalty = $credit->PAID_PENALTY ?? 0;

            $newMaxPrincipal = $oldMaxPrincipal + $bayarPokok;
            $newPaidPrincipal = $oldPaidPrincipal + $bayarPokok;
            $newPaidInterest = $oldPaidInterest + $bayarBunga;
            $newPaidPinalty = $oldPaidPinalty + $bayarDenda;

            $credit->update([
                "MAX_PRINCIPAL" => $newMaxPrincipal,
                "PAID_PRINCIPAL" => $newPaidPrincipal,
                "PAID_INTEREST" => $newPaidInterest,
                "PAID_PENALTY" => $newPaidPinalty,
            ]);
        }

        $items = [
            AccKeys::PEL_POKOK => $bayarPokok,
            AccKeys::PEL_BUNGA => $bayarBunga,
            AccKeys::PEL_DENDA => $bayarDenda,
        ];

        foreach ($items as $accKey => $amount) {
            if ($amount > 0) {
                M_CreditTransaction::create([
                    'ID'          => Uuid::uuid7()->toString(),
                    'LOAN_NUMBER' => $request->LOAN_NUMBER,
                    'ACC_KEYS'    => $accKey,
                    'AMOUNT'      => $amount,
                    'CREATED_BY'  => $request->user()->id,
                    'CREATED_AT'  => Carbon::now('Asia/Jakarta'),
                ]);
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

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    function proccessKwitansiDetail($request, $loan_number, $no_inv)
    {
        $creditSchedules =  M_CreditSchedule::from('credit_schedule AS a')
            ->leftJoin('arrears AS b', function ($join) {
                $join->on('b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                    ->on('b.START_DATE', '=', 'a.PAYMENT_DATE');
            })
            ->where('a.LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('a.PAID_FLAG', '!=', 'PAID')
                    ->orWhereNotIn('b.STATUS_REC', ['S', 'D'])
                    ->orWhereNull('b.STATUS_REC');
            })
            ->orderBy('a.PAYMENT_DATE', 'ASC')
            ->select(
                'a.LOAN_NUMBER',
                'a.INSTALLMENT_COUNT',
                'a.PAYMENT_DATE',
                'a.PRINCIPAL',
                'a.INTEREST',
                'a.INSTALLMENT',
                'a.PRINCIPAL_REMAINS',
                'a.PAYMENT_VALUE_PRINCIPAL',
                'a.PAYMENT_VALUE_INTEREST',
                'a.DISCOUNT_PRINCIPAL',
                'a.DISCOUNT_INTEREST',
                'a.INSUFFICIENT_PAYMENT',
                'a.PAYMENT_VALUE',
                'a.PAID_FLAG'
            )
            ->get();

        $this->principalCalculate($request, $loan_number, $no_inv, $creditSchedules);
        $this->interestCalculate($request, $loan_number, $no_inv, $creditSchedules);
        $arrears = M_Arrears::where(['LOAN_NUMBER' => $loan_number, 'STATUS_REC' => 'A'])->orderBy('START_DATE', 'ASC')->get();
        $this->arrearsCalculate($request, $loan_number, $no_inv, $arrears);
    }

    private function principalCalculate($request, $loan_number, $no_inv, $creditSchedule)
    {
        $remainingPayment = $request->BAYAR_POKOK;
        $remainingDiscount = $request->DISKON_POKOK;

        foreach ($creditSchedule as $res) {
            $valBefore = $res->{'PAYMENT_VALUE_PRINCIPAL'};
            $getAmount = $res->{'PRINCIPAL'};

            $remainingToPay = $getAmount - $valBefore;

            if ($remainingToPay > 0) {
                if ($remainingPayment >= $remainingToPay) {
                    $newPaymentValue = $remainingToPay;
                    $remainingPayment -= $remainingToPay;
                } else {
                    $newPaymentValue = $remainingPayment;
                    $remainingPayment = 0;
                }

                // Apply the payment to the schedule
                $param['BAYAR_POKOK'] = $newPaymentValue;
                $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);

                // // Handle the discount if applicable
                if ($remainingDiscount > 0) {
                    $remainingToDiscount = $getAmount - ($valBefore + $newPaymentValue);

                    if ($remainingDiscount >= $remainingToDiscount) {
                        $param['DISKON_POKOK'] = $remainingToDiscount;
                        $remainingDiscount -= $remainingToDiscount;
                    } else {
                        $param['DISKON_POKOK'] = $remainingDiscount;
                        $remainingDiscount = 0;
                    }

                    $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
                }
            }
        }

        if ($remainingPayment > 0) {
            $param['BAYAR_POKOK'] = $remainingPayment;
        }

        if ($remainingDiscount > 0) {
            $param['DISKON_POKOK'] = $remainingDiscount;
        }
    }

    private function interestCalculate($request, $loan_number, $no_inv, $creditSchedule)
    {
        $remainingPayment = $request->BAYAR_BUNGA;

        foreach ($creditSchedule as $res) {
            $valBefore = $res->PAYMENT_VALUE_INTEREST;
            $getAmount = $res->INTEREST;

            if ($valBefore < $getAmount) {
                $remainingToPay = $getAmount - $valBefore;

                if ($remainingPayment >= $remainingToPay) {
                    $newPaymentValue = $getAmount - $valBefore;
                    $remainingPayment -= $remainingToPay;
                } else {
                    $newPaymentValue = $remainingPayment;
                    $remainingPayment = 0;
                }

                $param = [
                    'BAYAR_BUNGA' => $newPaymentValue,
                    'DISKON_BUNGA' => 0,
                ];

                if ($newPaymentValue == $getAmount) {
                    $param['DISKON_BUNGA'] = 0;
                } else {
                    $param['DISKON_BUNGA'] = $getAmount - ($valBefore + $newPaymentValue);
                }

                $this->insertKwitansiDetail(
                    $loan_number,
                    $no_inv,
                    $res,
                    $param
                );
            }
        }
    }

    private function arrearsCalculate($request, $loan_number, $no_inv, $arrears)
    {
        $remainingPayment = $request->BAYAR_DENDA;
        $remainingDiscount = $request->DISKON_DENDA;

        foreach ($arrears as $res) {
            $valBefore = $res->{'PAID_PENALTY'};
            $getAmount = $res->{'PAST_DUE_PENALTY'};

            $remainingToPay = $getAmount - $valBefore;

            if ($remainingToPay > 0) {
                if ($remainingPayment >= $remainingToPay) {
                    $newPaymentValue = $remainingToPay;
                    $remainingPayment -= $remainingToPay;
                } else {
                    $newPaymentValue = $remainingPayment;
                    $remainingPayment = 0;
                }

                $param['BAYAR_DENDA'] = $newPaymentValue;
                $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);

                if ($remainingDiscount > 0) {
                    $remainingToDiscount = $getAmount - ($valBefore + $newPaymentValue);

                    if ($remainingDiscount >= $remainingToDiscount) {
                        $param['DISKON_DENDA'] = $remainingToDiscount;
                        $remainingDiscount -= $remainingToDiscount;
                    } else {
                        $param['DISKON_DENDA'] = $remainingDiscount;
                        $remainingDiscount = 0;
                    }

                    $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
                }
            }
        }

        if ($remainingPayment > 0) {
            $param['BAYAR_DENDA'] = $remainingPayment;
        }

        if ($remainingDiscount > 0) {
            $param['DISKON_DENDA'] = $remainingDiscount;
        }
    }

    function insertKwitansiDetail($loan_number, $no_inv, $res, $param = [])
    {
        $tgl_angsuran = $res['PAYMENT_DATE'] ?? $res['START_DATE'] ?? null;

        // Cek apakah data sudah ada berdasarkan no_invoice, tgl_angsuran, dan loan_number
        $checkDetail = M_KwitansiDetailPelunasan::where([
            'tgl_angsuran' => $tgl_angsuran,
            'loan_number' => $loan_number,
            'no_invoice' => $no_inv,
        ])->first();

        // Jika data sudah ada, update field yang relevan
        if ($checkDetail) {
            $fields = ['BAYAR_POKOK', 'DISKON_POKOK', 'BAYAR_BUNGA', 'DISKON_BUNGA', 'BAYAR_DENDA', 'DISKON_DENDA'];

            foreach ($fields as $field) {
                if (isset($param[$field])) {
                    // Update hanya jika nilai baru tidak sama dengan nilai yang sudah ada
                    if ($checkDetail->{strtolower($field)} != $param[$field]) {
                        $checkDetail->update([strtolower($field) => $param[$field]]);
                    }
                }
            }
        } else {
            // Jika data belum ada, buat baris baru
            $credit = M_CreditSchedule::where([
                'LOAN_NUMBER' => $loan_number,
                'PAYMENT_DATE' => $tgl_angsuran,
            ])->first();

            M_KwitansiDetailPelunasan::create([
                'no_invoice' => $no_inv ?? '',
                'loan_number' => $loan_number ?? '',
                'angsuran_ke' => $res['INSTALLMENT_COUNT'] ?? $credit->INSTALLMENT_COUNT ?? 0,
                'tgl_angsuran' => $tgl_angsuran,
                'installment' => $res['INSTALLMENT'] ?? 0,
                'bayar_pokok' => $param['BAYAR_POKOK'] ?? 0,
                'bayar_bunga' => $param['BAYAR_BUNGA'] ?? 0,
                'bayar_denda' => $param['BAYAR_DENDA'] ?? 0,
                'diskon_pokok' => $param['DISKON_POKOK'] ?? 0,
                'diskon_bunga' => $param['DISKON_BUNGA'] ?? 0,
                'diskon_denda' => $param['DISKON_DENDA'] ?? 0,
            ]);
        }
    }
}

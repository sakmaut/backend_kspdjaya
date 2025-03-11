<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_KwitansiPelunasan;
use App\Http\Resources\R_Pelunasan;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
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

class PelunasanController extends Controller
{
    public function index(Request $request)
    {
        try {

            $notrx = $request->query('notrx');
            $nama = $request->query('nama');
            $no_kontrak = $request->query('no_kontrak');

            $data = M_Kwitansi::where('PAYMENT_TYPE', 'pelunasan')->orderBy('CREATED_AT', 'DESC')->limit(10);

            if (!empty($notrx)) {
                $data = $data->where('NO_TRANSAKSI', 'like', '%' . $notrx . '%');
            }

            if (!empty($nama)) {
                $data = $data->where('NAMA', 'like', '%' . $nama . '%');
            }

            if (!empty($no_kontrak)) {
                $data = $data->where('LOAN_NUMBER', 'like', '%' . $no_kontrak . '%');
            }

            $results = $data->get();

            $dto = R_Pelunasan::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function checkCredit(Request $request)
    {
        try {

            $loan_number = $request->loan_number;

            $allQuery = "SELECT 
                            (a.PCPL_ORI - COALESCE(a.PAID_PRINCIPAL, 0)) AS SISA_POKOK,
                            b.INT_ARR AS TUNGGAKAN_BUNGA,
                            e.TUNGGAKAN_DENDA AS TUNGGAKAN_DENDA,
                            e.DENDA_TOTAL AS DENDA,
                            (COALESCE(a.PENALTY_RATE, 7.5) / 100) * (a.PCPL_ORI - COALESCE(a.PAID_PRINCIPAL, 0)) AS PINALTI,
                            d.DISC_BUNGA
                        FROM 
                            credit a
                            LEFT JOIN (
                                SELECT 
                                    LOAN_NUMBER,
                                    SUM(COALESCE(INTEREST, 0)) - SUM(COALESCE(PAYMENT_VALUE_INTEREST, 0)) AS INT_ARR
                                FROM 
                                    credit_schedule
                                WHERE 
                                    LOAN_NUMBER = '{$loan_number}' 
                                    AND PAYMENT_DATE <= (
                                        SELECT COALESCE(
                                            MIN(PAYMENT_DATE), 
                                            (SELECT MAX(PAYMENT_DATE) 
                                            FROM credit_schedule 
                                            WHERE LOAN_NUMBER = '{$loan_number}'  
                                            AND PAYMENT_DATE < NOW())
                                        )
                                        FROM credit_schedule
                                        WHERE LOAN_NUMBER = '{$loan_number}' 
                                        AND PAYMENT_DATE > NOW()
                                    )
                                GROUP BY LOAN_NUMBER
                            ) b ON b.LOAN_NUMBER = a.LOAN_NUMBER
                             LEFT JOIN (
                                        SELECT
                                            LOAN_NUMBER,
                                            COALESCE(SUM(INTEREST), 0) AS DISC_BUNGA
                                        FROM
                                            credit_schedule
                                        WHERE
                                            LOAN_NUMBER = '{$loan_number}'
                                            AND PAYMENT_DATE > (
                                                SELECT COALESCE(MIN(PAYMENT_DATE),
                                                    (SELECT MAX(PAYMENT_DATE)
                                                    FROM credit_schedule
                                                    WHERE LOAN_NUMBER = '{$loan_number}'
                                                    AND PAYMENT_DATE < NOW())
                                                )
                                                FROM credit_schedule
                                                WHERE LOAN_NUMBER = '{$loan_number}'
                                                AND PAYMENT_DATE > NOW()
                                            )
                                        GROUP BY LOAN_NUMBER
                            ) AS d ON d.LOAN_NUMBER = a.LOAN_NUMBER
                            LEFT JOIN (
                                SELECT 
                                    LOAN_NUMBER, 
                                    SUM(CASE WHEN STATUS_REC <> 'A' THEN COALESCE(PAST_DUE_PENALTY, 0) END) -
                                        SUM(CASE WHEN STATUS_REC <> 'A' THEN COALESCE(PAID_PENALTY, 0) END) AS TUNGGAKAN_DENDA,
                                    SUM(COALESCE(PAST_DUE_PENALTY, 0)) - SUM(COALESCE(PAID_PENALTY, 0)) AS DENDA_TOTAL
                                FROM 
                                    arrears
                                WHERE 
                                    LOAN_NUMBER = '{$loan_number}' 
                                GROUP BY LOAN_NUMBER
                            ) e ON e.LOAN_NUMBER = a.LOAN_NUMBER
                        WHERE 
                            a.LOAN_NUMBER = '{$loan_number}' ";

            $result = DB::select($allQuery);

            // $query2 = DB::select("
            //         select	sum(INTEREST-coalesce(PAYMENT_VALUE_INTEREST,0)) as DISC_BUNGA
            // 		from	credit_schedule
            // 		where	LOAN_NUMBER = '{$loan_number}'
            // 				and PAYMENT_DATE>now()
            // ");

            $processedResults = array_map(function ($item) {
                return [
                    'SISA_POKOK' => round(floatval($item->SISA_POKOK), 2),
                    'TUNGGAKAN_BUNGA' => round(floatval($item->TUNGGAKAN_BUNGA), 2),
                    'TUNGGAKAN_DENDA' => round(floatval($item->TUNGGAKAN_DENDA), 2),
                    'DENDA' => round(floatval($item->DENDA), 2),
                    'PINALTI' => round(floatval($item->PINALTI), 2),
                    'DISC_BUNGA' => round(floatval($item->DISC_BUNGA), 2)
                ];
            }, $result);

            // $discBunga = 0;
            // if (!empty($query2) && isset($query2[0]->DISC_BUNGA)) {
            //     $discBunga = round(floatval($query2[0]->DISC_BUNGA), 2);
            // }

            // foreach ($processedResults as &$processedResult) {
            //     $processedResult['DISC_BUNGA'] = $discBunga;
            // }

            return response()->json($processedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function processPayment(Request $request)
    {
        DB::beginTransaction();
        try {

            $check = $request->only(['BAYAR_POKOK', 'BAYAR_BUNGA', 'BAYAR_PINALTI', 'BAYAR_DENDA', 'DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            if (array_sum($check) == 0) {
                throw new Exception('Null Kabeh');
            }

            $loan_number = $request->LOAN_NUMBER;

            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            $status = "PENDING";

            // $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            // $status = "PAID";

            // if (array_sum($discounts) > 0 || strtolower($request->METODE_PEMBAYARAN) === 'transfer') {
            //     $status = "PENDING";
            // }

            if (!M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->exists()) {
                $this->saveKwitansi($request, $detail_customer, $no_inv, $status);
                $this->proccessKwitansiDetail($request, $loan_number, $no_inv);
            }

            $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
                ->where(function ($query) {
                    $query->where('PAID_FLAG', '!=', 'PAID')->orWhereNull('PAID_FLAG');
                })
                ->orderBy('PAYMENT_DATE', 'asc')
                ->get();

            $checkArr = M_Arrears::where([
                'LOAN_NUMBER' => $loan_number,
                'STATUS_REC' => 'A'
            ])->get();

            foreach ($creditSchedule as $res) {
                $res->update(['PAID_FLAG' => 'PENDING']);
            }

            foreach ($checkArr as $ress) {
                $ress->update(['STATUS_REC' => 'PENDING']);
            }

            $data = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            $dto = new R_KwitansiPelunasan($data);

            DB::commit();
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
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
            'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN ?? '',
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
        }

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Bayar Pelunasan Pinalty',
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? M_Branch::find($request->user()->branch_id)->CODE_NUMBER,
            'LOAN_NUM' => $loan_number ?? '',
            'ENTRY_DATE' => Carbon::now(),
            'TITLE' => 'Bayar Pelunasan Pinalty',
            'ORIGINAL_AMOUNT' => $request->BAYAR_PINALTI ?? 0,
            'END_DATE' => Carbon::now(),
            'USER_ID' => $user_id ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => Carbon::now()
        ]);

        if ($request->BAYAR_PINALTI != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN PINALTY', $request->BAYAR_PINALTI ?? 0);
        }

        if ($request->DISKON_PINALTI != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN DISKON PINALTY', $request->DISKON_PINALTI ?? 0);
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

    private function saveKwitansi($request, $customer, $no_inv, $status)
    {

        $checkKwitansiExist = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if ($checkKwitansiExist) {
            throw new Exception("Kwitansi Exist", 500);
        }

        $data = [
            "PAYMENT_TYPE" => 'pelunasan',
            "PAYMENT_ID" => $request->payment_id,
            "STTS_PAYMENT" => $status,
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
            "DISKON" => $request->PEMBULATAN,
            "KEMBALIAN" => $request->KEMBALIAN,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->id
        ];

        M_Kwitansi::create($data);

        $getCredit = M_Credit::where('LOAN_NUMBER', $request->LOAN_NUMBER)->first();

        if ($getCredit) {
            $getCredit->update([
                "PINALTY_PELUNASAN" => $request->BAYAR_PINALTI ?? 0,
                "DISKON_PINALTY_PELUNASAN" => $request->DISKON_PINALTI ?? 0,
            ]);
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
                    ->orWhereNotIn('b.STATUS_REC', ['S', 'D']);
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
            // Get the current value of the field and payment status
            $valBefore = $res->{'PAYMENT_VALUE_PRINCIPAL'};
            $getAmount = $res->{'PRINCIPAL'};

            $remainingToPay = $getAmount - $valBefore;

            // Proceed only if there's an amount left to pay
            if ($remainingToPay > 0) {
                // If enough payment is available to cover the remaining amount
                if ($remainingPayment >= $remainingToPay) {
                    // Full payment for the remaining amount
                    $newPaymentValue = $remainingToPay;
                    $remainingPayment -= $remainingToPay; // Subtract the paid amount
                } else {
                    // Partial payment
                    $newPaymentValue = $remainingPayment;
                    $remainingPayment = 0; // All payment used
                }

                // Apply the payment to the schedule
                $param['BAYAR_POKOK'] = $newPaymentValue;
                $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);

                // Handle the discount if applicable
                if ($remainingDiscount > 0) {
                    $remainingToDiscount = $getAmount - ($valBefore + $newPaymentValue);

                    if ($remainingDiscount >= $remainingToDiscount) {
                        $param['DISKON_POKOK'] = $remainingToDiscount; // Full discount
                        $remainingDiscount -= $remainingToDiscount; // Subtract the used discount
                    } else {
                        $param['DISKON_POKOK'] = $remainingDiscount; // Partial discount
                        $remainingDiscount = 0; // No discount left
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
                    $newPaymentValue = $getAmount;
                    $remainingPayment -= $remainingToPay;
                } else {
                    $newPaymentValue = $valBefore + $remainingPayment;
                    $remainingPayment = 0;
                }

                $param = [
                    'BAYAR_BUNGA' => $newPaymentValue,
                    'DISKON_BUNGA' => 0,
                ];

                if ($newPaymentValue == $getAmount) {
                    $param['DISKON_BUNGA'] = 0;
                } else {
                    $param['DISKON_BUNGA'] = $getAmount - $newPaymentValue;
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
        $this->calculateArrears(
            $request->BAYAR_DENDA,
            $request->DISKON_DENDA,
            $arrears,
            'PAST_DUE_PENALTY',
            'PAID_PENALTY',
            'BAYAR_DENDA',
            'DISKON_DENDA',
            $loan_number,
            $no_inv
        );
    }

    private function calculateArrears($paymentAmount, $discountAmount, $schedule, $fieldKey, $valueKey, $paymentParam, $discountParam, $loan_number, $no_inv)
    {
        $remainingPayment = $paymentAmount;
        $remainingDiscount = $discountAmount;

        foreach ($schedule as $res) {
            $valBefore = $res->{$valueKey};
            $getAmount = $res->{$fieldKey};

            if ($valBefore < $getAmount) {
                // Calculate the amount left to pay
                $remainingToPay = $getAmount - $valBefore;

                // If enough payment is available to cover the remaining amount
                if ($remainingPayment >= $remainingToPay) {
                    $newPaymentValue = $getAmount; // Full payment
                    $remainingPayment -= $remainingToPay; // Subtract the paid amount
                } else {
                    $newPaymentValue = $valBefore + $remainingPayment;
                    $remainingPayment = 0;
                }

                if ($paymentAmount != 0) {
                    $param[$paymentParam] = $newPaymentValue;
                    $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
                }

                // Handle the discount if applicable
                if ($remainingDiscount > 0) {
                    $remainingToDiscount = $getAmount - ($valBefore + $newPaymentValue);

                    if ($remainingDiscount >= $remainingToDiscount) {
                        $param[$discountParam] = $remainingToDiscount; // Full discount
                        $remainingDiscount -= $remainingToDiscount; // Subtract the used discount
                    } else {
                        $param[$discountParam] = $remainingDiscount; // Partial discount
                        $remainingDiscount = 0; // No discount left
                    }

                    $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
                }
            }
        }


        if ($remainingPayment > 0) {
            $param[$paymentParam] = $remainingPayment;
        }

        if ($remainingDiscount > 0) {
            $param[$discountParam] = $remainingDiscount;
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

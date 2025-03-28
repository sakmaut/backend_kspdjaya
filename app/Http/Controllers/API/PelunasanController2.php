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

class PelunasanController2 extends Controller
{
    private function checkCredit($loan_number, $date)
    {
        try {

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
                                            AND PAYMENT_DATE < '$date')
                                        )
                                        FROM credit_schedule
                                        WHERE LOAN_NUMBER = '{$loan_number}' 
                                        AND PAYMENT_DATE > '$date'
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
                                                    AND PAYMENT_DATE < '$date')
                                                )
                                                FROM credit_schedule
                                                WHERE LOAN_NUMBER = '{$loan_number}'
                                                AND PAYMENT_DATE > '$date'
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

            return response()->json($processedResults, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function getDetail(Request $request)
    {
        DB::beginTransaction();
        try {

            $inv = $request->no_invoice;

            $cekINV = M_Kwitansi::where('NO_TRANSAKSI', $inv)->first();

            if (!$cekINV) {
                throw new Exception("Invoice Not Found", 500);
            }

            $arrearsData = [];
            $getCrditSchedule = "   SELECT LOAN_NUMBER,PAYMENT_DATE,PRINCIPAL,INTEREST,INSTALLMENT,PAYMENT_VALUE_PRINCIPAL,PAYMENT_VALUE_INTEREST
                                    FROM credit_schedule 
                                    WHERE LOAN_NUMBER = '$cekINV->LOAN_NUMBER'
                                        AND (PAID_FLAG IS NULL OR PAID_FLAG = '')
                                    ORDER BY PAYMENT_DATE ASC ";


            $updateArrears = DB::select($getCrditSchedule);

            foreach ($updateArrears as $list) {
                $date = date('Y-m-d', strtotime($cekINV->CREATED_AT));
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
                    'CREATED_AT' => Carbon::now('Asia/Jakarta')
                ];
            }

            if (!empty($arrearsData)) {
                foreach ($arrearsData as $data) {
                    $existingArrears = M_Arrears::where([
                        'LOAN_NUMBER' => $data['LOAN_NUMBER'],
                        'START_DATE' => $data['START_DATE'],
                        'STATUS_REC' => 'A'
                    ])->first();

                    if ($existingArrears) {
                        $existingArrears->update([
                            'PAST_DUE_PENALTY' => $data['PAST_DUE_PENALTY'] ?? 0,
                            'UPDATED_AT' => Carbon::now('Asia/Jakarta')
                        ]);
                    } else {
                        $getNow = date('Y-m-d', strtotime($cekINV->CREATED_AT));

                        if ($data['START_DATE'] < $getNow) {
                            M_Arrears::create($data);
                        }
                    }
                }
            }

            $getDetail = $this->checkCredit($cekINV->LOAN_NUMBER, date('Y-m-d', strtotime($cekINV->CREATED_AT)));

            $build = [];
            foreach ($getDetail->original as $list) {
                $build = [
                    "SISA_POKOK" => $list['SISA_POKOK'],
                    "TUNGGAKAN_BUNGA" => $list['TUNGGAKAN_BUNGA'],
                    "PINALTI" => $list['PINALTI'],
                    "DENDA" => $list['DENDA'],
                    "TUNGGAKAN_DENDA" => $list['TUNGGAKAN_DENDA'],
                    "DISC_BUNGA" => $list['DISC_BUNGA'],
                    'UANG_PELANGGAN' => $cekINV['JUMLAH_UANG'],
                    'LOAN_NUMBER' => $cekINV['LOAN_NUMBER'],
                    'INVOICE' => $cekINV['NO_TRANSAKSI'],
                    'METODE_PEMBAYARAN' => $cekINV['METODE_PEMBAYARAN'],
                    'BRANCH_CODE' => $cekINV['BRANCH_CODE'],
                    'PINALTY_PELUNASAN' => $cekINV['PINALTY_PELUNASAN'],
                    'DISKON_PINALTY_PELUNASAN' => $cekINV['DISKON_PINALTY_PELUNASAN'],
                    'CREATED_BY' => $cekINV['CREATED_BY'],
                    'CREATED_AT' => $cekINV['CREATED_AT']
                ];
            }

            DB::commit();
            return response()->json($build, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function propel(Request $request)
    {

        DB::beginTransaction();
        try {
            $loan_number = $request['LOAN_NUMBER'];
            $no_inv = $request['INVOICE'];

            $this->proccessKwitansiDetail($request, $loan_number, $no_inv);

            $this->proccess($request, $loan_number, $no_inv, 'PAID');

            DB::commit();
            return response()->json("MUACH MUACHH MUACHHH", 200);
        } catch (\Exception $e) {
            DB::rollback();
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
                $this->updateCredit($request, $res, $loan_number);
            }
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

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Pelunasan Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request['METODE_PEMBAYARAN'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::find($request['BRANCH_CODE'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $res['loan_number'] ?? '',
            'ENTRY_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null,
            'TITLE' => 'Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'ORIGINAL_AMOUNT' => $originalAmount,
            'START_DATE' => $res['tgl_angsuran'] ?? '',
            'END_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null,
            'USER_ID' => $request['CREATED_BY'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null
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
            'PAYMENT_METHOD' => $request['METODE_PEMBAYARAN'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::find($request['BRANCH_CODE'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number ?? '',
            'ENTRY_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null,
            'TITLE' => 'Bayar Pelunasan Pinalty',
            'ORIGINAL_AMOUNT' => $request['BAYAR_PINALTI'] ?? 0,
            'END_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null,
            'USER_ID' => $request['CREATED_BY'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => date('Y-m-d H:i:s', strtotime($request['CREATED_AT'])) ?? null
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

    function updateCredit($request, $res, $loan_number)
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
                'PINALTY_PELUNASAN' => $request['BAYAR_PINALTI'],
                'DISKON_PINALTY_PELUNASAN' => $request['DISKON_PINALTI'],
                'STATUS' => 'D',
                'STATUS_REC' => 'PT',
                'END_DATE' => now()
            ]);
        } else {
            throw new Exception("Credit Not Found", 404);
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

    // private function calculateArrears($paymentAmount, $discountAmount, $schedule, $fieldKey, $valueKey, $paymentParam, $discountParam, $loan_number, $no_inv)
    // {
    //     $remainingPayment = $paymentAmount;
    //     $remainingDiscount = $discountAmount;

    //     foreach ($schedule as $res) {
    //         $valBefore = $res->{$valueKey};
    //         $getAmount = $res->{$fieldKey};

    //         if ($valBefore < $getAmount) {
    //             // Calculate the amount left to pay
    //             $remainingToPay = $getAmount - $valBefore;

    //             // If enough payment is available to cover the remaining amount
    //             if ($remainingPayment >= $remainingToPay) {
    //                 $newPaymentValue = $getAmount; // Full payment
    //                 $remainingPayment -= $remainingToPay; // Subtract the paid amount
    //             } else {
    //                 $newPaymentValue = $valBefore + $remainingPayment;
    //                 $remainingPayment = 0;
    //             }

    //             if ($paymentAmount != 0) {
    //                 $param[$paymentParam] = $newPaymentValue;
    //                 $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
    //             }

    //             // Handle the discount if applicable
    //             if ($remainingDiscount > 0) {
    //                 $remainingToDiscount = $getAmount - ($valBefore + $newPaymentValue);

    //                 if ($remainingDiscount >= $remainingToDiscount) {
    //                     $param[$discountParam] = $remainingToDiscount; // Full discount
    //                     $remainingDiscount -= $remainingToDiscount; // Subtract the used discount
    //                 } else {
    //                     $param[$discountParam] = $remainingDiscount; // Partial discount
    //                     $remainingDiscount = 0; // No discount left
    //                 }

    //                 $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);
    //             }
    //         }
    //     }


    //     if ($remainingPayment > 0) {
    //         $param[$paymentParam] = $remainingPayment;
    //     }

    //     if ($remainingDiscount > 0) {
    //         $param[$discountParam] = $remainingDiscount;
    //     }
    // }

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

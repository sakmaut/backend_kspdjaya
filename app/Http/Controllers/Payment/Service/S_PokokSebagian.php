<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_PokokSebagian;
use App\Http\Resources\R_Kwitansi;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Services\Credit\CreditService;
use App\Services\Kwitansi\KwitansiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class S_PokokSebagian
{
    protected $repository;
    protected $kwitansiService;

    public function __construct(
        R_PokokSebagian $repository,
        KwitansiService $kwitansiService
    ) {
        $this->repository = $repository;
        $this->kwitansiService = $kwitansiService;
    }

    public function getAllCreditInstallment($request)
    {
        $results = $this->repository->getAllData($request);

        $data = array_map(function ($item) {
            return [
                'SISA_POKOK' => round(floatval($item->SISA_POKOK), 2),
                'TUNGGAKAN_BUNGA' => round(floatval($item->TUNGGAKAN_BUNGA), 2),
                'TUNGGAKAN_DENDA' => round(floatval($item->TUNGGAKAN_DENDA), 2),
                'DENDA' => round(floatval($item->DENDA), 2),
                'DISC_BUNGA' => round(floatval($item->DISC_BUNGA), 2)
            ];
        }, $results);

        return $data;
    }

    public function processPayment($request)
    {
        $check = $request->only(['BAYAR_POKOK', 'BAYAR_BUNGA', 'BAYAR_DENDA', 'DISKON_POKOK', 'DISKON_BUNGA', 'DISKON_DENDA']);

        if (array_sum($check) == 0) {
            throw new Exception('Parameter Is 0');
        }

        $kwitansi = $this->kwitansiService->create($request, 'pokok_sebagian');
        $this->proccessKwitansiDetail($request, $kwitansi);

        if ($kwitansi->STTS_PAYMENT == 'PAID') {
            return $this->processPokokBungaMenurun($request, $kwitansi);
        }

        // return new R_Kwitansi($kwitansi);
    }

    private function proccessKwitansiDetail($request, $kwitansi)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansi->NO_TRANSAKSI;

        $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->select('ID', 'INSTALLMENT_COUNT', 'PAYMENT_DATE', 'INSTALLMENT', 'PRINCIPAL', 'INTEREST', 'PAYMENT_VALUE_PRINCIPAL', 'PAYMENT_VALUE_INTEREST')
            ->get();

        $interest = $this->buildInterest($request, $creditSchedule);

        if ($request->BAYAR_POKOK != 0) {
            $data = $this->buildPrincipal($request, $creditSchedule);

            $lastZeroIndex = null;
            $currentMonth = date('Y-m');
            $lastIndex = null;

            foreach ($interest as $index => &$item) {
                $paymentMonth = date('Y-m', strtotime($item['PAYMENT_DATE']));

                if ($item['BAYAR_BUNGA'] == 0) {
                    if ($paymentMonth > $currentMonth) {
                        $item['INSTALLMENT'] = $data['angsuran'];

                        if ($data['angsuran'] == 0) {
                            $item['PAID_FLAG'] = "PAID";
                        }

                        $lastZeroIndex = $index;
                    }
                } else {
                    $lastIndex = $index;
                }
            }

            if ($lastZeroIndex !== null) {
                $interest[$lastZeroIndex]['PRINCIPAL'] = $data['pokok'];
            }

            if ($lastIndex !== null) {
                $interest[$lastIndex]['PRINCIPAL'] = $data['bayar'];
            }
        }

        foreach ($interest as $value) {

            $data = [
                'no_invoice' => $no_inv ?? '',
                'loan_number' => $loan_number ?? '',
                'angsuran_ke' => $value['INSTALLMENT_COUNT'] ?? 0,
                'tgl_angsuran' => $value['PAYMENT_DATE'] ?? null,
                'installment' => $value['INSTALLMENT'] ?? 0,
                'bayar_pokok' => $value['PRINCIPAL'] ?? 0,
                'bayar_bunga' => $value['BAYAR_BUNGA'] ?? 0,
                'bayar_denda' => $value['BAYAR_DENDA'] ?? 0,
                'diskon_pokok' => $value['DISKON_POKOK'] ?? 0,
                'diskon_bunga' => $value['DISKON_BUNGA'] ?? 0,
                'diskon_denda' => $value['DISKON_DENDA'] ?? 0,
            ];

            M_KwitansiDetailPelunasan::create($data);
        }
    }

    private function buildInterest($request, $creditSchedule)
    {
        $payment = $request->BAYAR_BUNGA;
        $interest = [];

        foreach ($creditSchedule as $res) {
            $paidInterest = $res->PAYMENT_VALUE_INTEREST ?? 0;
            $totalInterest = $res->INTEREST;
            $remainingInterest = $totalInterest - $paidInterest;

            $paidNow = min($payment, $remainingInterest);
            $payment -= $paidNow;

            $discount = $totalInterest - ($paidInterest + $paidNow);

            $interest[] = [
                'ID' => $res->ID,
                'PRINCIPAL' => floatval($res->PRINCIPAL),
                'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                'INSTALLMENT' => floatval($res->INSTALLMENT),
                'PAYMENT_DATE' => $res->PAYMENT_DATE,
                'BAYAR_BUNGA' => $remainingInterest > 0 ? $paidNow : 0,
                'DISKON_BUNGA' => $remainingInterest > 0 ? $discount : $totalInterest,
            ];
        }

        return $interest;
    }

    private function buildPrincipal($request, $data)
    {

        $payment = $request->BAYAR_POKOK;
        $maxInstallment = null;
        foreach ($data as $row) {
            if ($maxInstallment === null || $row['INSTALLMENT_COUNT'] > $maxInstallment['INSTALLMENT_COUNT']) {
                $maxInstallment = $row;
            }
        }

        $sisa_pokok = floatval($maxInstallment['PRINCIPAL']) - floatval($payment);
        $calc = round($sisa_pokok * (3 / 100), 2);

        $schedule = [
            'bayar' => $payment,
            'pokok' => $sisa_pokok,
            'bunga' => $calc,
            'angsuran' => floatval($calc)
        ];

        return $schedule;
    }

    // private function buildPrincipal($request, $data)
    // {
    //     $payment = $request->BAYAR_POKOK;

    //     $maxInstallment = null;
    //     foreach ($data as $row) {
    //         if ($maxInstallment === null || $row['INSTALLMENT_COUNT'] > $maxInstallment['INSTALLMENT_COUNT']) {
    //             $maxInstallment = $row;
    //         }
    //     }

    //     return $maxInstallment;
    //     die;

    //     // $maxItem = array_filter($interest, function ($item) use ($maxInstallment) {
    //     //     return $item['INSTALLMENT_COUNT'] === $maxInstallment;
    //     // });

    //     // $maxItem = reset($maxItem);

    //     // $lastValid = null;
    //     // for ($i = count($interest) - 1; $i >= 0; $i--) {
    //     //     if ($interest[$i]['BAYAR_BUNGA'] != 0) {
    //     //         $lastValid = [
    //     //             'INSTALLMENT_COUNT' => $interest[$i]['INSTALLMENT_COUNT']
    //     //         ];
    //     //         break;
    //     //     }
    //     // }

    //     // $count = 0;
    //     // foreach ($interest as $item) {
    //     //     if ($item['BAYAR_BUNGA'] == 0) {
    //     //         $count++;
    //     //     }
    //     // }

    //     // $sisa_pokok = $maxItem ? $maxItem['PRINCIPAL'] - $payment : 0;
    //     // $calc = round($sisa_pokok * (3 / 100), 2);

    //     // $data = [
    //     //     'SUBMISSION_VALUE' => $sisa_pokok,
    //     //     'TOTAL_ADMIN' => 0,
    //     //     'INSTALLMENT' => $calc,
    //     //     'TENOR' => $count ?? 1,
    //     //     'START_FROM' => $lastValid != null ? $lastValid['INSTALLMENT_COUNT'] : 1
    //     // ];

    //     // $data_credit_schedule = $this->buildDataPokokSebagian($data);

    //     // return $data_credit_schedule;
    // }

    private function processPokokBungaMenurun($request, $kwitansiDetail)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansiDetail->NO_TRANSAKSI;

        $kwitansi = M_Kwitansi::with(['kwitansi_pelunasan_detail', 'branch'])->where([
            'LOAN_NUMBER' => $loan_number,
            'NO_TRANSAKSI' => $no_inv
        ])->first();

        if (!$kwitansi) return;


        foreach ($kwitansi->kwitansi_pelunasan_detail as $val) {
            $creditS = M_CreditSchedule::where(
                [
                    'LOAN_NUMBER' =>  $loan_number,
                    'INSTALLMENT_COUNT' => $val['angsuran_ke'],
                    'PAYMENT_DATE' => $val['tgl_angsuran'],
                ]
            )->first();

            $creditS->update([
                'PRINCIPAL' => $val['bayar_pokok'],
                'INTEREST' => $val['installment'],
                'PAYMENT_VALUE_PRINCIPAL' => $val['bayar_pokok'],
                'PAYMENT_VALUE_INTEREST' => $val['bayar_bunga'],
            ]);
        }

        // return $kwitansi->kwitansi_pelunasan_detail;
        // die;

        // $credit_schedule = M_CreditSchedule::where([
        //     'LOAN_NUMBER' => $loan_number,
        //     'INSTALLMENT_COUNT' => $kwitansi->kwitansi_structur_detail->map(function ($res) {
        //         return intval($res->angsuran_ke);
        //     })
        // ])->first();

        // if (!$credit_schedule) return;

        // $getPrincipalPay = (float) $kwitansi->kwitansi_structur_detail->sum(function ($detail) {
        //     return floatval($detail->bayar_angsuran);
        // });

        // $uid = Uuid::uuid7()->toString();

        // $paymentData = [
        //     'ID' => $uid,
        //     'ACC_KEY' => 'pokok_sebagian',
        //     'STTS_RCRD' => 'PAID',
        //     'INVOICE' => $no_inv ?? '',
        //     'NO_TRX' => $request->uid ?? '',
        //     'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? '',
        //     'BRANCH' =>  $kwitansi->branch['CODE_NUMBER'] ?? '',
        //     'LOAN_NUM' => $loan_number,
        //     'VALUE_DATE' => null,
        //     'ENTRY_DATE' => now(),
        //     'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
        //     'TITLE' => 'Pembayaran Pokok Sebagian',
        //     'ORIGINAL_AMOUNT' => $getPrincipalPay,
        //     'OS_AMOUNT' => $os_amount ?? 0,
        //     'START_DATE' => $tgl_angsuran ?? null,
        //     'END_DATE' => now(),
        //     'USER_ID' => $kwitansi->CREATED_BY ?? $request->user()->id,
        //     'AUTH_BY' => $request->user()->fullname ?? '',
        //     'AUTH_DATE' => now(),
        //     'ARREARS_ID' => $res['id_arrear'] ?? '',
        //     'BANK_NAME' => round(microtime(true) * 1000)
        // ];

        // $existing = M_Payment::where($paymentData)->first();

        // if (!$existing) {
        //     M_Payment::create($paymentData);
        // }

        // $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $getPrincipalPay);
        // M_PaymentDetail::create($data);

        // $principalPay = ($credit_schedule->PAYMENT_VALUE_PRINCIPAL + $getPrincipalPay);

        // $credit_schedule->update([
        //     'PRINCIPAL' => $principalPay,
        //     'INSTALLMENT' => $principalPay + $credit_schedule->INTEREST,
        //     'PAYMENT_VALUE_PRINCIPAL' => $principalPay,
        //     'INSUFFICIENT_PAYMENT' => (floatval($credit_schedule->INTEREST) - floatval($credit_schedule->PAYMENT_VALUE_INTEREST)),
        //     'PAYMENT_VALUE' => (floatval($credit_schedule->PAYMENT_VALUE) + floatval($getPrincipalPay))
        // ]);

        // $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $getPrincipalPay]);

        // $creditSchedulesUpdate = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
        //     ->where(function ($query) {
        //         $query->where('PAID_FLAG', '!=', 'PAID')
        //             ->orWhere('PAID_FLAG', '=', '')
        //             ->orWhereNull('PAID_FLAG');
        //     })
        //     ->where(function ($query) {
        //         $query->whereNull('PAYMENT_VALUE_PRINCIPAL')
        //             ->orWhere('PAYMENT_VALUE_PRINCIPAL', '=', '');
        //     })
        //     ->orderBy('INSTALLMENT_COUNT', 'ASC')
        //     ->orderBy('PAYMENT_DATE', 'ASC')
        //     ->get();

        // if ($creditSchedulesUpdate->isEmpty()) return;

        // $totalSisaPokok = $creditSchedulesUpdate->sum('PRINCIPAL');

        // $sisa_pokok = $totalSisaPokok - $getPrincipalPay;
        // $sisa_pokok = max(0, $sisa_pokok);

        // $getNewTenor = count($creditSchedulesUpdate);
        // $calc = round($sisa_pokok * (3 / 100), 2);

        // $data = new \stdClass();
        // $data->SUBMISSION_VALUE = $sisa_pokok;
        // $data->TOTAL_ADMIN = 0;
        // $data->INSTALLMENT = $calc;
        // $data->TENOR = $getNewTenor;
        // $data->START_FROM = $creditSchedulesUpdate->first()->INSTALLMENT_COUNT;

        // // $data_credit_schedule = $this->buildDataPokokSebagian($data);

        // foreach ($creditSchedulesUpdate as $index => $schedule) {
        //     // $updateData = $data_credit_schedule[$index];

        //     $updateArray = [
        //         'PRINCIPAL' => $updateData['pokok'],
        //         'INTEREST' => $updateData['bunga'],
        //         'INSTALLMENT' => $updateData['total_angsuran'],
        //         'PRINCIPAL_REMAINS' => $updateData['baki_debet'],
        //     ];

        //     if ((float)$updateData['total_angsuran'] == 0) {
        //         $updateArray['PAID_FLAG'] = 'PAID';
        //     }

        //     $schedule->update($updateArray);
        // }

        // $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
        //     ->where(function ($query) {
        //         $query->where('PAID_FLAG', '')
        //             ->orWhereNull('PAID_FLAG');
        //     })
        //     ->sum('INTEREST');

        // $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        // $check_credit->update([
        //     'INTRST_ORI' => $totalInterest ?? 0
        // ]);
    }

    // private function buildDataPokokSebagian($data)
    // {
    //     $schedule = [];
    //     $ttal_bayar = ($data['SUBMISSION_VALUE'] + $data['TOTAL_ADMIN']);
    //     $angsuran_bunga = $data['INSTALLMENT'];
    //     $term = ceil($data['TENOR']);
    //     $baki_debet = $ttal_bayar;

    //     $startInstallment = $data->START_FROM ?? 1;

    //     for ($i = 0; $i < $term; $i++) {
    //         $pokok = 0;

    //         if ($i == $term - 1) {
    //             $pokok = $ttal_bayar;
    //         }

    //         $total_angsuran = $pokok + $angsuran_bunga;

    //         $schedule[] = [
    //             'angsuran_ke' => $startInstallment + $i,
    //             'baki_debet_awal' => floatval($baki_debet),
    //             'pokok' => floatval($pokok),
    //             'bunga' => floatval($angsuran_bunga),
    //             'total_angsuran' => floatval($total_angsuran),
    //             'baki_debet' => floatval($baki_debet - $pokok)
    //         ];

    //         $baki_debet -= $pokok;
    //     }

    //     return $schedule;
    // }

    private function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    private function addCreditPaid($loan_number, array $data)
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
}

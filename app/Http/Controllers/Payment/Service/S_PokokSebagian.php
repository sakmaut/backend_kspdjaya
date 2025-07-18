<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_PokokSebagian;
use App\Http\Resources\R_Kwitansi;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Services\Kwitansi\KwitansiService;
use Exception;
use Ramsey\Uuid\Uuid;

class S_PokokSebagian
{
    protected $repository;
    protected $kwitansiService;
    protected $s_creditScheduleBefore;
    protected $s_creditBefore;

    public function __construct(
        R_PokokSebagian $repository,
        KwitansiService $kwitansiService,
        S_CreditScheduleBefore $s_creditScheduleBefore,
        S_CreditBefore $s_creditBefore
    ) {
        $this->repository = $repository;
        $this->kwitansiService = $kwitansiService;
        $this->s_creditScheduleBefore = $s_creditScheduleBefore;
        $this->s_creditBefore = $s_creditBefore;
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
        // return $this->proccessKwitansiDetail($request, $kwitansi);

        $this->proccessKwitansiDetail($request, $kwitansi);

        if ($kwitansi->STTS_PAYMENT == 'PAID') {
            $this->processPokokBungaMenurun($request, $kwitansi);
        }

        return new R_Kwitansi($kwitansi);
    }

    private function proccessKwitansiDetail($request, $kwitansi)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansi->NO_TRANSAKSI;

        $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->get();

        foreach ($creditSchedule as $value) {
            $this->s_creditScheduleBefore->created($value, $no_inv);
        }

        $credit = M_Credit::select(
            'ID',
            'LOAN_NUMBER',
            'PCPL_ORI',
            'INTRST_ORI',
            'PAID_PRINCIPAL',
            'PAID_INTEREST',
            'PAID_PENALTY',
            'DUE_PRINCIPAL',
            'DUE_INTEREST',
            'DUE_PENALTY',
            'DISCOUNT_PRINCIPAL',
            'DISCOUNT_INTEREST',
            'DISCOUNT_PENALTY',
            'PINALTY_PELUNASAN',
            'DISKON_PINALTY_PELUNASAN'
        )->where('LOAN_NUMBER', $loan_number)->first();

        $this->s_creditBefore->created($credit, $no_inv);

        $build = $this->buildPayment($request, $creditSchedule);

        foreach ($build as $value) {
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

    private function buildPayment($request, $creditSchedule)
    {
        $paymentBunga = $request->BAYAR_BUNGA ?? 0;
        $paymentPokok = $request->BAYAR_POKOK ?? 0;

        $currentMonth = date('m');
        $currentYear = date('Y');

        $data = [];
        $sisaPaymentBunga = $paymentBunga;

        foreach ($creditSchedule as $res) {
            $paidInterest = floatval($res->PAYMENT_VALUE_INTEREST ?? 0);

            if (strtoupper($res->PAID_FLAG) === 'PAID') {
                $principal = floatval($res->PRINCIPAL);
                $interest = floatval($res->INTEREST);
                $data[] = [
                    'ID' => $res->ID,
                    'PRINCIPAL' => $principal,
                    'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                    'INSTALLMENT' => $principal + $interest,
                    'INTEREST' => $interest,
                    'PAYMENT_DATE' => $res->PAYMENT_DATE,
                    'BAYAR_BUNGA' => 0,
                    'DISKON_BUNGA' => 0
                ];
                continue;
            }

            $totalInterest = floatval($res->INTEREST);
            $installmentRaw = floatval($res->INSTALLMENT);
            $installmentAdjusted = max(0, $installmentRaw - $paidInterest);
            $maxBayarBunga = min($installmentAdjusted, $totalInterest - $paidInterest);

            $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
            $sisaPaymentBunga -= $paidNow;

            $discount = $installmentAdjusted - $paidNow;

            $data[] = [
                'ID' => $res->ID,
                'PRINCIPAL' => floatval($res->PRINCIPAL),
                'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                'INSTALLMENT' => $installmentRaw,
                'INTEREST' => $installmentAdjusted,
                'PAYMENT_DATE' => $res->PAYMENT_DATE,
                'BAYAR_BUNGA' => $paidNow,
                'DISKON_BUNGA' => max(0, $discount)
            ];
        }

        // Jika ada BAYAR_POKOK
        if ($paymentPokok > 0) {
            $currentPaymentIndex = null;
            $maxIndex = null;

            foreach ($data as $index => $row) {
                $rowMonth = date('m', strtotime($row['PAYMENT_DATE']));
                $rowYear = date('Y', strtotime($row['PAYMENT_DATE']));

                if ($rowMonth == $currentMonth && $rowYear == $currentYear && $currentPaymentIndex === null) {
                    $currentPaymentIndex = $index;
                }

                if ($maxIndex === null || $row['INSTALLMENT_COUNT'] > $data[$maxIndex]['INSTALLMENT_COUNT']) {
                    $maxIndex = $index;
                }
            }

            if ($currentPaymentIndex === null && count($data) > 0) {
                $currentPaymentIndex = 0;
            }

            if ($currentPaymentIndex !== null) {
                $data[$currentPaymentIndex]['PRINCIPAL'] += $paymentPokok;
                $data[$currentPaymentIndex]['INSTALLMENT'] += $paymentPokok;
            }

            $calc = 0;
            if ($maxIndex !== null) {
                $data[$maxIndex]['PRINCIPAL'] -= $paymentPokok;
                $sisaPokok = floatval($data[$maxIndex]['PRINCIPAL']);
                $calc = round($sisaPokok * (3 / 100), 2);
            }

            $sisaPaymentBunga = $paymentBunga;
            $minCount = isset($currentPaymentIndex) ? $data[$currentPaymentIndex]['INSTALLMENT_COUNT'] : null;

            foreach ($data as $index => $row) {
                if (isset($creditSchedule[$index]) && strtoupper($creditSchedule[$index]->PAID_FLAG) === 'PAID') {
                    continue;
                }

                $paymentValueInterest = floatval($creditSchedule[$index]->PAYMENT_VALUE_INTEREST ?? 0);

                // Jangan update INTEREST dan INSTALLMENT jika PAYMENT_VALUE_INTEREST sudah diisi
                if ($minCount !== null && $row['INSTALLMENT_COUNT'] > $minCount && $paymentValueInterest == 0) {
                    $data[$index]['INTEREST'] = $calc;
                    $data[$index]['INSTALLMENT'] = $calc + $data[$index]['PRINCIPAL'];
                }

                // Perhitungan ulang BUNGA hanya jika belum dibayar semua
                $maxBayarBunga = min($data[$index]['INTEREST'], $row['INTEREST']);
                $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
                $sisaPaymentBunga -= $paidNow;

                $data[$index]['BAYAR_BUNGA'] = $paidNow;
                $data[$index]['DISKON_BUNGA'] = max(0, $data[$index]['INTEREST'] - $paidNow);
            }
        }

        return $data;
    }

    public function processPokokBungaMenurun($request, $kwitansiDetail)
    {
        $loanNumber = $request->LOAN_NUMBER;
        $noTransaksi = $kwitansiDetail->NO_TRANSAKSI;

        $kwitansi = $this->getKwitansi($loanNumber, $noTransaksi);
        if (!$kwitansi) return;

        $credit = M_Credit::with(['arrears'])->where('LOAN_NUMBER', $loanNumber)->first();

        $details = collect($kwitansi->kwitansi_pelunasan_detail);
        $finalPrincipalRemains = $details->sortByDesc('angsuran_ke')->first()['bayar_pokok'];
        $totalPrincipalPaid = $details->sum('bayar_pokok');
        $maxDetail = $details->sortByDesc('angsuran_ke')->first();

        foreach ($details as $detail) {
            $this->processDetail($loanNumber, $detail, $finalPrincipalRemains, $totalPrincipalPaid, $maxDetail, $kwitansi, $credit);
        }

        $this->updateCreditStatus($request, $credit, $loanNumber);
    }

    private function getKwitansi($loanNumber, $noTransaksi)
    {
        return M_Kwitansi::with(['kwitansi_pelunasan_detail', 'branch:ID,CODE,CODE_NUMBER'])
            ->select('LOAN_NUMBER', 'METODE_PEMBAYARAN', 'BRANCH_CODE', 'NO_TRANSAKSI', 'CREATED_BY')
            ->where('LOAN_NUMBER', $loanNumber)
            ->where('NO_TRANSAKSI', $noTransaksi)
            ->first();
    }

    private function processDetail($loanNumber, $detail, $finalPrincipalRemains, $totalPrincipalPaid, $maxDetail)
    {
        $schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loanNumber,
            'INSTALLMENT_COUNT' => $detail['angsuran_ke'],
            'PAYMENT_DATE' => $detail['tgl_angsuran'],
        ])->first();

        if (!$schedule) {
            throw new Exception("Credit schedule not found for angsuran ke-{$detail['angsuran_ke']}", 1);
        }

        $maxInstallment = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->max('INSTALLMENT_COUNT');
        $isLastInstallment = intval($detail['angsuran_ke']) === intval($maxInstallment);

        $paidPrincipal = floatval($detail['bayar_pokok']);
        $paidInterest = floatval($detail['bayar_bunga']);
        $interest = floatval($schedule->INTEREST);
        $beforePaidInterest = floatval($schedule->PAYMENT_VALUE_INTEREST);
        $insufficientPayment = floatval($schedule->INSUFFICIENT_PAYMENT);
        $paymentValue = floatval($schedule->PAYMENT_VALUE);

        $installmentValue = floatval($detail['installment']);
        $setInterest = $installmentValue - $paidPrincipal;
        $totalPrincipal  = $paidPrincipal;
        $totalInterest  = $beforePaidInterest + $paidInterest;
        $isPaid = $paidInterest == $interest;

        $fields = [
            'PRINCIPAL' => $paidPrincipal,
            'INTEREST' => max(0, $setInterest),
            'INSTALLMENT' => $installmentValue,
            'PRINCIPAL_REMAINS' => $isPaid ? $totalPrincipalPaid : $finalPrincipalRemains,
            'PAYMENT_VALUE_PRINCIPAL' => !$isLastInstallment ? $totalPrincipal : 0,
            'PAYMENT_VALUE_INTEREST' => $totalInterest
        ];

        if (!$isLastInstallment && ($paidPrincipal != 0 || $paidInterest != 0)) {
            $fields['INSUFFICIENT_PAYMENT'] = ($totalPrincipal + $totalInterest) - $installmentValue;
            $fields['PAYMENT_VALUE'] = $totalPrincipal + $totalInterest;
            $fields['PAID_FLAG'] = $installmentValue - ($totalPrincipal + $totalInterest) == 0 ? 'PAID' : '';
        } else if ($paidPrincipal == 0 && $paidInterest == 0 && $installmentValue == 0 || floatval($maxDetail->bayar_pokok ?? 0) == 0) {
            $fields['PAID_FLAG'] = 'PAID';
            $fields['INSUFFICIENT_PAYMENT'] = $insufficientPayment != 0 ? $insufficientPayment : 0;
            $fields['PAYMENT_VALUE'] = $paymentValue > 0 ? $paymentValue : 0;
        }

        $schedule->update($fields);

        // $this->addPayment($request, $kwitansi, $detail);
    }

    private function updateCreditStatus($request, $credit, $loanNumber)
    {

        $allPaid =  M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->where('PAID_FLAG', '!=', 'PAID')->count();

        $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->sum('INTEREST');

        $totalOriginal = $credit->PCPL_ORI + $credit->INTRST_ORI;
        $totalPaid = $credit->PAID_PRINCIPAL + $credit->PAID_INTEREST;

        $credit->update([
            'INTRST_ORI' => $totalInterest ?? 0,
            'STATUS_REC' => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'PT' : $credit->STATUS_REC,
            'STATUS'     => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'D'  : $credit->STATUS,
            'PAID_PRINCIPAL' => $credit->PAID_PRINCIPAL + $request->BAYAR_POKOK,
            'PAID_INTEREST' => $credit->PAID_INTEREST + $request->BAYAR_BUNGA,
            'PAID_PENALTY' => $credit->PAID_PENALTY + $request->BAYAR_DENDA,
        ]);
    }


    public function addPayment($request, $kwitansi, $data)
    {
        $uid = Uuid::uuid7()->toString();
        $loanNumber = $request->LOAN_NUMBER;
        $bayarPokok = floatval($data['bayar_pokok'] ?? 0);
        $bayarBunga = floatval($data['bayar_bunga'] ?? 0);
        $totalPayment = $bayarPokok + $bayarBunga;

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => 'pokok_sebagian',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $kwitansi->NO_TRANSAKSI ?? '',
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? '',
            'BRANCH' => $kwitansi->branch['CODE_NUMBER'] ?? '',
            'LOAN_NUM' => $loanNumber,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Pembayaran Pokok Sebagian',
            'ORIGINAL_AMOUNT' => $totalPayment,
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran ?? null,
            'END_DATE' => now(),
            'USER_ID' => $kwitansi->CREATED_BY ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? ''
        ];

        if (!M_Payment::where($paymentData)->first()) {
            M_Payment::create($paymentData);
        }

        M_PaymentDetail::create([
            'PAYMENT_ID' => $uid,
            'ACC_KEYS' => 'ANGSURAN_POKOK_SEBAGIAN',
            'ORIGINAL_AMOUNT' => $bayarPokok
        ]);

        M_PaymentDetail::create([
            'PAYMENT_ID' => $uid,
            'ACC_KEYS' => 'ANGSURAN_BUNGA',
            'ORIGINAL_AMOUNT' => $bayarBunga
        ]);
    }

    public function cancel($loan_number, $no_inv)
    {
        // Update status kwitansi jadi 'CANCEL'
        M_Kwitansi::where('LOAN_NUMBER', $loan_number)
            ->where('NO_TRANSAKSI', '>=', $no_inv)
            ->update(['STTS_PAYMENT' => 'CANCEL']);

        // Ambil data sebelumnya
        $scheduleBefore = $this->s_creditScheduleBefore->getDataCreditSchedule($no_inv);
        $creditBefore = $this->s_creditBefore->getDataCredit($no_inv);

        // Update data kredit
        M_Credit::where('LOAN_NUMBER', $loan_number)->update([
            'STATUS_REC' => "AC",
            'STATUS' => "A",
            'INTRST_ORI' => $creditBefore->INTRST_ORI,
            'PAID_PRINCIPAL' => $creditBefore->PAID_PRINCIPAL,
            'PAID_INTEREST' => $creditBefore->PAID_INTEREST,
            'PAID_PENALTY' => $creditBefore->PAID_PENALTY,
            'DISCOUNT_PRINCIPAL' => $creditBefore->DISCOUNT_PRINCIPAL,
            'DISCOUNT_INTEREST' => $creditBefore->DISCOUNT_INTEREST,
            'DISCOUNT_PENALTY' => $creditBefore->DISCOUNT_PENALTY,
        ]);

        // Reset dan insert ulang jadwal kredit
        M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->delete();
        foreach ($scheduleBefore as $value) {
            $fields = [
                'LOAN_NUMBER' => $value['LOAN_NUMBER'],
                'INSTALLMENT_COUNT' => $value['INSTALLMENT_COUNT'],
                'PAYMENT_DATE' => $value['PAYMENT_DATE'],
                'PRINCIPAL' => $value['PRINCIPAL'],
                'INTEREST' => $value['INTEREST'],
                'INSTALLMENT' => $value['INSTALLMENT'],
                'PRINCIPAL_REMAINS' => $value['PRINCIPAL_REMAINS'],
                'PAYMENT_VALUE_PRINCIPAL' => $value['PAYMENT_VALUE_PRINCIPAL'],
                'PAYMENT_VALUE_INTEREST' => $value['PAYMENT_VALUE_INTEREST'],
                'DISCOUNT_PRINCIPAL' => $value['DISCOUNT_PRINCIPAL'],
                'DISCOUNT_INTEREST' => $value['DISCOUNT_INTEREST'],
                'INSUFFICIENT_PAYMENT' => $value['INSUFFICIENT_PAYMENT'],
                'PAYMENT_VALUE' => $value['PAYMENT_VALUE'],
                'PAID_FLAG' => $value['PAID_FLAG']
            ];

            M_CreditSchedule::create($fields);
        }
    }


    // public function cancel($loan_number, $no_inv)
    // {
    //     $kwitansi = M_Kwitansi::with(['kwitansi_pelunasan_detail'])->select('ID', 'PAYMENT_TYPE', 'STTS_PAYMENT', 'NO_TRANSAKSI', 'LOAN_NUMBER')
    //         ->where('LOAN_NUMBER', $loan_number)
    //         ->where('NO_TRANSAKSI', '>=', $no_inv)
    //         ->orderBy('NO_TRANSAKSI', 'asc')
    //         ->get();

    //     $kwitansi->update(['STTS_PAYMENT' => 'CANCEL']);

    //     $creditScheduleBefore = $this->s_creditScheduleBefore->getDataCreditSchedule($no_inv);
    //     $creditBefore = $this->s_creditBefore->getDataCredit($no_inv);

    //     $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first()->update([
    //         'INTRST_ORI' => $creditBefore->INTRST_ORI,
    //         'PAID_PRINCIPAL' => $creditBefore->PAID_PRINCIPAL,
    //         'PAID_INTEREST' => $creditBefore->PAID_INTEREST,
    //         'PAID_PENALTY' => $creditBefore->PAID_PENALTY,
    //         'DISCOUNT_PRINCIPAL' => $creditBefore->DISCOUNT_PRINCIPAL,
    //         'DISCOUNT_INTEREST' => $creditBefore->DISCOUNT_INTEREST,
    //         'DISCOUNT_PENALTY' => $creditBefore->DISCOUNT_PENALTY,
    //     ]);

    //     M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->delete();

    //     foreach ($creditScheduleBefore as $value) {
    //         $fields = [
    //             'LOAN_NUMBER' => $value['LOAN_NUMBER'],
    //             'INSTALLMENT_COUNT' => $value['INSTALLMENT_COUNT'],
    //             'PAYMENT_DATE' => $value['PAYMENT_DATE'],
    //             'PRINCIPAL' => $value['PRINCIPAL'],
    //             'INTEREST' => $value['INTEREST'],
    //             'INSTALLMENT' => $value['INSTALLMENT'],
    //             'PRINCIPAL_REMAINS' => $value['PRINCIPAL_REMAINS'],
    //             'PAYMENT_VALUE_PRINCIPAL' => $value['PAYMENT_VALUE_PRINCIPAL'],
    //             'PAYMENT_VALUE_INTEREST' => $value['PAYMENT_VALUE_INTEREST'],
    //             'DISCOUNT_PRINCIPAL' => $value['DISCOUNT_PRINCIPAL'],
    //             'DISCOUNT_INTEREST' => $value['DISCOUNT_INTEREST'],
    //             'INSUFFICIENT_PAYMENT' => $value['INSUFFICIENT_PAYMENT'],
    //             'PAYMENT_VALUE' => $value['PAYMENT_VALUE'],
    //             'PAID_FLAG' => $value['PAID_FLAG']
    //         ];

    //         M_CreditSchedule::create($fields);
    //     }
    // }
}

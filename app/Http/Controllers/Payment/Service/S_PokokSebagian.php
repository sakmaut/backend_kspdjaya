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
        return $this->proccessKwitansiDetail($request, $kwitansi);

        // if ($kwitansi->STTS_PAYMENT == 'PAID') {
        //     $this->processPokokBungaMenurun($request, $kwitansi);
        // }

        // return new R_Kwitansi($kwitansi);
    }

    private function proccessKwitansiDetail($request, $kwitansi)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansi->NO_TRANSAKSI;

        $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->select('ID', 'INSTALLMENT_COUNT', 'PAYMENT_DATE', 'INSTALLMENT', 'PRINCIPAL', 'INTEREST', 'PAYMENT_VALUE_PRINCIPAL', 'PAYMENT_VALUE_INTEREST', 'PAID_FLAG')
            ->get();

        $build = $this->buildPayment($request, $creditSchedule);

        return $build;

        // foreach ($build as $value) {
        //     $data = [
        //         'no_invoice' => $no_inv ?? '',
        //         'loan_number' => $loan_number ?? '',
        //         'angsuran_ke' => $value['INSTALLMENT_COUNT'] ?? 0,
        //         'tgl_angsuran' => $value['PAYMENT_DATE'] ?? null,
        //         'installment' => $value['INSTALLMENT'] ?? 0,
        //         'bayar_pokok' => $value['PRINCIPAL'] ?? 0,
        //         'bayar_bunga' => $value['BAYAR_BUNGA'] ?? 0,
        //         'bayar_denda' => $value['BAYAR_DENDA'] ?? 0,
        //         'diskon_pokok' => $value['DISKON_POKOK'] ?? 0,
        //         'diskon_bunga' => $value['DISKON_BUNGA'] ?? 0,
        //         'diskon_denda' => $value['DISKON_DENDA'] ?? 0,
        //     ];

        //     M_KwitansiDetailPelunasan::create($data);
        // }
    }

    private function buildPayment($request, $creditSchedule)
    {
        $paymentBunga = $request->BAYAR_BUNGA ?? 0;
        $paymentPokok = $request->BAYAR_POKOK ?? 0;

        $today = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        $data = [];
        $sisaPaymentBunga = $paymentBunga;

        foreach ($creditSchedule as $res) {
            if (strtoupper($res->PAID_FLAG) === 'PAID') {
                $data[] = [
                    'ID' => $res->ID,
                    'PRINCIPAL' => floatval($res->PRINCIPAL),
                    'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                    'INSTALLMENT' => floatval($res->INSTALLMENT),
                    'INTEREST' => floatval($res->INTEREST),
                    'PAYMENT_DATE' => $res->PAYMENT_DATE,
                    'BAYAR_BUNGA' => 0,
                    'DISKON_BUNGA' => 0
                ];
                continue;
            }

            $paidInterest = $res->PAYMENT_VALUE_INTEREST ?? 0;
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

                if ($minCount !== null && $row['INSTALLMENT_COUNT'] > $minCount) {
                    $data[$index]['INSTALLMENT'] = $calc;
                    $data[$index]['INTEREST'] = $calc;
                }

                $maxBayarBunga = min($data[$index]['INTEREST'], $row['INTEREST']);
                $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
                $sisaPaymentBunga -= $paidNow;

                $data[$index]['BAYAR_BUNGA'] = $paidNow;
                $data[$index]['DISKON_BUNGA'] = max(0, $data[$index]['INTEREST'] - $paidNow);
            }
        }

        return $data;
    }

    private function processPokokBungaMenurun($request, $kwitansiDetail)
    {
        $loanNumber = $request->LOAN_NUMBER;
        $noTransaksi = $kwitansiDetail->NO_TRANSAKSI;

        $kwitansi = $this->getKwitansi($loanNumber, $noTransaksi);
        if (!$kwitansi) return;

        $credit = M_Credit::with(['arrears'])->where('LOAN_NUMBER', $loanNumber)->first();

        $details = collect($kwitansi->kwitansi_pelunasan_detail);
        $finalPrincipalRemains = $details->sortByDesc('angsuran_ke')->first()['bayar_pokok'];
        $totalPrincipalPaid = $details->sum('bayar_pokok');

        foreach ($details as $detail) {
            $this->processDetail($loanNumber, $detail, $finalPrincipalRemains, $totalPrincipalPaid, $request, $kwitansi, $credit);
        }

        // $this->updateCreditStatus($credit, $loanNumber);
    }

    private function getKwitansi($loanNumber, $noTransaksi)
    {
        return M_Kwitansi::with(['kwitansi_pelunasan_detail', 'branch:ID,CODE,CODE_NUMBER'])
            ->select('LOAN_NUMBER', 'METODE_PEMBAYARAN', 'BRANCH_CODE', 'NO_TRANSAKSI', 'CREATED_BY')
            ->where('LOAN_NUMBER', $loanNumber)
            ->where('NO_TRANSAKSI', $noTransaksi)
            ->first();
    }

    private function processDetail($loanNumber, $detail, $finalPrincipalRemains, $totalPrincipalPaid, $request, $kwitansi, $credit)
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
        $beforepaidPrincipal = floatval($schedule->PAYMENT_VALUE_PRINCIPAL);
        $beforePaidInterest = floatval($schedule->PAYMENT_VALUE_INTEREST);

        $installmentValue = $paidPrincipal + floatval($detail['installment']);
        $totalPaid = $paidPrincipal + $paidInterest;
        $totalPrincipal  = $beforepaidPrincipal + $paidPrincipal;
        $totalInterest  = $beforePaidInterest + $paidInterest;
        $isPaid = $paidInterest == $interest;

        $fields = [
            // 'PRINCIPAL' => $isLastInstallment && !$isPaid ? $paidPrincipal : $paidPrincipal,
            'PRINCIPAL' => $paidPrincipal,
            'INTEREST' => $detail['installment'],
            'INSTALLMENT' => $installmentValue,
            'PRINCIPAL_REMAINS' => $isPaid ? $totalPrincipalPaid : $finalPrincipalRemains,
            'PAYMENT_VALUE_PRINCIPAL' => !$isLastInstallment ? $totalPrincipal : 0,
            'PAYMENT_VALUE_INTEREST' => $totalInterest
        ];

        if (!$isLastInstallment && ($paidPrincipal != 0 || $paidInterest != 0)) {
            $fields['INSUFFICIENT_PAYMENT'] = $installmentValue - ($totalPrincipal + $totalInterest);
            $fields['PAYMENT_VALUE'] = $totalPrincipal + $totalInterest;
            $fields['PAID_FLAG'] = $installmentValue - ($totalPrincipal + $totalInterest) == 0 ? 'PAID' : '';
        }

        $schedule->update($fields);

        if ($paidInterest != 0) {
            $this->addPayment($request, $kwitansi, $detail);
            $credit->update([
                'PAID_PRINCIPAL' => $credit->PAID_PRINCIPAL + $paidPrincipal,
                'PAID_INTEREST' => $credit->PAID_INTEREST + $paidInterest,
                'PAID_PENALTY' => $credit->PAID_PENALTY + floatval($detail['bayar_denda']),
            ]);
        }
    }

    private function updateCreditStatus($credit, $loanNumber)
    {
        $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->sum('INTEREST');

        $totalOriginal = $credit->PCPL_ORI + $credit->INTRST_ORI;
        $totalPaid = $credit->PAID_PRINCIPAL + $credit->PAID_INTEREST;

        $credit->update([
            'INTRST_ORI' => $totalInterest ?? 0,
            'STATUS_REC' => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty()) ? 'PT' : $credit->STATUS_REC,
            'STATUS'     => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty()) ? 'D'  : $credit->STATUS,
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

    public function cancel($request)
    {
        $loan_number = $request->loan_number;
        $no_inv = $request->no_inv;

        $getAllTrx = M_Kwitansi::where('LOAN_NUMBER', $loan_number)
            ->where('NO_TRANSAKSI', '>=', $no_inv)
            ->orderBy('NO_TRANSAKSI', 'asc')
            ->get();

        return $getAllTrx;
    }
}

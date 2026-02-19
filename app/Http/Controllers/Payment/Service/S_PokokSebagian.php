<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_PokokSebagian;
use App\Http\Controllers\Repositories\TasksLogging\TasksRepository;
use App\Http\Resources\R_Kwitansi;
use App\Models\M_Arrears;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Services\Kwitansi\KwitansiService;
use Carbon\Carbon;
use Exception;
use Ramsey\Uuid\Uuid;

class S_PokokSebagian
{
    protected $repository;
    protected $kwitansiService;
    protected $s_creditScheduleBefore;
    protected $s_creditBefore;
    protected $s_arrearsBefore;
    protected $taskslogging;

    public function __construct(
        R_PokokSebagian $repository,
        KwitansiService $kwitansiService,
        S_CreditScheduleBefore $s_creditScheduleBefore,
        S_CreditBefore $s_creditBefore,
        S_ArrearsBefore $s_arrearsBefore,
        TasksRepository $taskslogging,
    ) {
        $this->repository = $repository;
        $this->kwitansiService = $kwitansiService;
        $this->s_creditScheduleBefore = $s_creditScheduleBefore;
        $this->s_creditBefore = $s_creditBefore;
        $this->s_arrearsBefore = $s_arrearsBefore;
        $this->taskslogging = $taskslogging;
    }

    public function processPayment($request)
    {
        $check = $request->only([
            'BAYAR_POKOK',
            'BAYAR_BUNGA',
            'BAYAR_DENDA',
            'DENDA',
            'DISKON_POKOK',
            'DISKON_BUNGA',
            'DISKON_DENDA'
        ]);

        if (array_sum($check) == 0) {
            throw new Exception('Parameter Is 0');
        }

        $kwitansi = $this->kwitansiService->create($request, 'pokok_sebagian');
        $this->proccessKwitansiDetail($request, $kwitansi);

        $metode = trim($kwitansi->METODE_PEMBAYARAN ?? '');

        if ($metode === '') {
            throw new Exception("METODE PEMBAYARAN KOSONG!!");
        }

        $PaymentMethod = strtolower($metode) === 'cash';
        $userName = $request->user()->fullname ?? '';
        $noTrans  = $kwitansi->NO_TRANSAKSI ?? '';

        if (checkPosition($request) && $PaymentMethod === true) {
            $this->taskslogging->create(
                $request,
                "Pembayaran Cash Bunga Menurun $userName",
                'request_payment',
                $noTrans,
                'PENDING',
                'Pembayaran Cash Bunga Menurun'
            );
        }

        if ($request->FLAG_DISKON == true) {
            $this->taskslogging->create(
                $request,
                "Diskon Pembayaran Cash Bunga Menurun",
                'request_payment',
                $noTrans,
                'PENDING',
                'Diskon Pembayaran Cash Bunga Menurun'
            );
        }

        if ($kwitansi->STTS_PAYMENT === 'PAID') {
            $this->processPokokBungaMenurun($request, $kwitansi);
        }

        return new R_Kwitansi($kwitansi);
    }

    private function proccessKwitansiDetail($request, $kwitansi)
    {
        $loanNumber = $request->LOAN_NUMBER;
        $noInv = $kwitansi->NO_TRANSAKSI;

        $creditSchedules = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)
            ->orderBy('PAYMENT_DATE', 'asc')
            ->get();

        foreach ($creditSchedules as $schedule) {
            $this->s_creditScheduleBefore->created($schedule, $noInv);
        }

        // Backup Arrears
        $arrearsList = M_Arrears::where('LOAN_NUMBER', $loanNumber)->get();
        foreach ($arrearsList as $arrear) {
            $this->s_arrearsBefore->created($arrear, $noInv);
        }

        // Backup Credit Info
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
        )->where('LOAN_NUMBER', $loanNumber)->first();

        $this->s_creditBefore->created($credit, $noInv);

        if($request->FLAG_DISKON){
            $payments = $this->buildPaymentFlagDiscount($request, $creditSchedules);
        }else{
            $payments = $this->buildPayment($request, $creditSchedules);
        }

        $setDataDetail = [];

        foreach ($payments as $payment) {
            $setDataDetail[] = [
                'id'   => Uuid::uuid7()->toString(),
                'no_invoice'   => $noInv,
                'loan_number'  => $loanNumber,
                'angsuran_ke'  => $payment['INSTALLMENT_COUNT'] ?? 0,
                'tgl_angsuran' => $payment['PAYMENT_DATE'] ?? null,
                'principal'    => $payment['PRINCIPAL'] ?? 0,
                'interest'     => $payment['INTEREST'] ?? 0,
                'installment'  => ($payment['PRINCIPAL'] ?? 0) + ($payment['INTEREST'] ?? 0),
                'bayar_pokok'  => $payment['BAYAR_POKOK'] ?? 0,
                'bayar_bunga'  => $payment['BAYAR_BUNGA'] ?? 0,
                'bayar_denda'  => $payment['BAYAR_DENDA'] ?? 0,
                'diskon_pokok' => $payment['DISKON_POKOK'] ?? 0,
                'diskon_bunga' => $payment['DISKON_BUNGA'] ?? 0,
                'diskon_denda' => $payment['DISKON_DENDA'] ?? 0
            ];
        }

        M_KwitansiDetailPelunasan::insert($setDataDetail);
    }

    private function buildPaymentFlagDiscount($request, $creditSchedule)
    {
        $paymentBunga = floatval($request->BAYAR_BUNGA ?? 0);
        $paymentPokok = floatval($request->BAYAR_POKOK ?? 0);

        $currentDate = date('Y-m-d');

        $data = [];
        $sisaPaymentBunga = $paymentBunga;

        foreach ($creditSchedule as $res) {

            $paidInterest = floatval($res->PAYMENT_VALUE_INTEREST ?? 0);

            if (strtoupper($res->PAID_FLAG) === 'PAID') {
                $principal = floatval($res->PRINCIPAL);
                $interest = floatval($res->INTEREST);
                $data[] = [
                    'ID' => $res->ID,
                    'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                    'PAYMENT_DATE' => $res->PAYMENT_DATE,
                    'PRINCIPAL' => $principal,
                    'INTEREST' => $interest,
                    'INSTALLMENT' => $principal + $interest,  
                    'BAYAR_BUNGA' => 0,
                    'BAYAR_POKOK' => 0,
                    'BAYAR_DENDA' => $bayarDenda ?? 0,
                    'DISKON_BUNGA' => 0,
                    'DISKON_POKOK' => 0, 
                   
                ];
                continue;
            }

            $totalInterest = floatval($res->INTEREST);
            $installmentRaw = floatval($res->INSTALLMENT);
            $installmentAdjusted = max(0, $installmentRaw - $paidInterest);
            $maxBayarBunga = min($installmentAdjusted, $totalInterest - $paidInterest);

            $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
            $sisaPaymentBunga -= $paidNow;

            $discount = $totalInterest - $paidNow;

            $data[] = [
                'ID' => $res->ID,
                'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                'PAYMENT_DATE' => $res->PAYMENT_DATE,
                'PRINCIPAL' => floatval($res->PRINCIPAL),
                'INTEREST' => $totalInterest,
                'INSTALLMENT' => $installmentRaw,
                'BAYAR_BUNGA' => $paidNow,
                'BAYAR_POKOK' => 0,
                'BAYAR_DENDA' => $bayarDenda ?? 0,
                'DISKON_BUNGA' => max(0, $discount),
                'DISKON_POKOK' => 0
            ];
        }

        $semuaSudahLewat = true;
        foreach ($data as $row) {
            if ($row['PAYMENT_DATE'] >= $currentDate) {
                $semuaSudahLewat = false;
                break;
            }
        }

        if ($paymentPokok > 0) {
            $maxIndex = null;

            foreach ($data as $index => $row) {
                if ($maxIndex === null || $row['INSTALLMENT_COUNT'] > $data[$maxIndex]['INSTALLMENT_COUNT']) {
                    $maxIndex = $index;
                }
            }

            if ($semuaSudahLewat && $maxIndex !== null) {
                $data[$maxIndex]['INSTALLMENT'] = $res->PRINCIPAL + $totalInterest;
                $data[$maxIndex]['BAYAR_POKOK'] = $paymentPokok;
            } else {
                // Jalankan proses biasa
                $currentMonth = date('m');
                $currentYear = date('Y');
                $currentPaymentIndex = null;

                foreach ($data as $index => $row) {
                    $rowMonth = date('m', strtotime($row['PAYMENT_DATE']));
                    $rowYear = date('Y', strtotime($row['PAYMENT_DATE']));

                    if ($rowMonth == $currentMonth && $rowYear == $currentYear && $currentPaymentIndex === null) {
                        $currentPaymentIndex = $index;
                    }
                }

                if ($currentPaymentIndex === null && count($data) > 0) {
                    $currentPaymentIndex = 0;
                }

                if ($currentPaymentIndex !== null) {
                    $data[$currentPaymentIndex]['PRINCIPAL'] += $paymentPokok;
                    // $data[$currentPaymentIndex]['INSTALLMENT'] += $paymentPokok;
                    if ($maxIndex !== null) {
                        $data[$maxIndex]['BAYAR_POKOK'] = $paymentPokok;
                    }
                }

                $calc = 0;
                if ($maxIndex !== null) {
                    $data[$maxIndex]['PRINCIPAL'] -= $paymentPokok;
                    $sisaPokok = floatval($data[$maxIndex]['PRINCIPAL']);
                    $value = round($sisaPokok * (3 / 100), 2);

                    $calc = ceil($value / 1000) * 1000;
                }

                $sisaPaymentBunga = $paymentBunga;
                $minCount = isset($currentPaymentIndex) ? $data[$currentPaymentIndex]['INSTALLMENT_COUNT'] : null;

                foreach ($data as $index => $row) {
                    if (isset($creditSchedule[$index]) && strtoupper($creditSchedule[$index]->PAID_FLAG) === 'PAID') {
                        continue;
                    }

                    $paymentValueInterest = floatval($creditSchedule[$index]->PAYMENT_VALUE_INTEREST ?? 0);

                    if (
                        $minCount !== null &&
                        $row['INSTALLMENT_COUNT'] > $minCount &&
                        $paymentValueInterest == 0 &&
                        floatval($row['BAYAR_BUNGA']) == 0
                    ) {
                        $data[$index]['INTEREST'] = $calc;
                        $data[$index]['INSTALLMENT'] = $calc + $data[$index]['PRINCIPAL'];
                    }

                    $maxBayarBunga = min($data[$index]['INTEREST'], $row['INTEREST']);
                    $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
                    $sisaPaymentBunga -= $paidNow;

                    $data[$index]['BAYAR_BUNGA'] = $paidNow;
                    $data[$index]['DISKON_BUNGA'] = max(0, $data[$index]['INTEREST'] - $paidNow);
                }
            }
        }

        foreach ($data as $index => $row) {
            $bayarPokok = floatval($row['BAYAR_POKOK'] ?? 0);
            if ($bayarPokok <= 0) continue;

            $principalAsli = floatval($creditSchedule[$index]->PRINCIPAL ?? 0);

            $data[$index]['DISKON_POKOK'] = max(0, $principalAsli - $bayarPokok);
            $data[$index]['PRINCIPAL'] = $principalAsli;
            $data[$index]['INSTALLMENT'] = $principalAsli + $data[$index]['INTEREST'];
        }

        return $data;
    }

    private function buildPayment($request, $creditSchedule)
    {
        $paymentBunga = $request->BAYAR_BUNGA ?? 0;
        $paymentPokok = $request->BAYAR_POKOK ?? 0;
        $paymentDenda = $request->BAYAR_DENDA ?? 0;

        $currentDate = date('Y-m-d');

        $data = [];
        $sisaPaymentBunga = $paymentBunga;
        $sisaPaymentDenda = $paymentDenda;

        foreach ($creditSchedule as $res) {

            $arrears = M_Arrears::where('LOAN_NUMBER', $request->LOAN_NUMBER)
                ->whereDate('START_DATE', $res->PAYMENT_DATE)
                ->where('STATUS_REC', 'A')
                ->first();

            $sisaDendaRow = 0;
            if ($arrears) {
                $sisaDendaRow = floatval($arrears->PAST_DUE_PENALTY) - floatval($arrears->PAID_PENALTY);
            }

            $bayarDenda = 0;
            if ($sisaPaymentDenda > 0 && $sisaDendaRow > 0) {
                $bayarDenda = min($sisaPaymentDenda, $sisaDendaRow);
                $sisaPaymentDenda -= $bayarDenda;
            }

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
                    'DISKON_BUNGA' => 0,
                    'BAYAR_POKOK' => 0,
                    'BAYAR_DENDA' => $bayarDenda ?? 0,
                ];
                continue;
            }

            $totalInterest = floatval($res->INTEREST);
            $installmentRaw = floatval($res->INSTALLMENT);
            $installmentAdjusted = max(0, $installmentRaw - $paidInterest);
            $maxBayarBunga = min($installmentAdjusted, $totalInterest - $paidInterest);

            $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
            $sisaPaymentBunga -= $paidNow;

            $discount = $totalInterest - $paidNow;

            $data[] = [
                'ID' => $res->ID,
                'PRINCIPAL' => floatval($res->PRINCIPAL),
                'INSTALLMENT_COUNT' => $res->INSTALLMENT_COUNT,
                'INSTALLMENT' => $installmentRaw,
                'INTEREST' => $totalInterest,
                'PAYMENT_DATE' => $res->PAYMENT_DATE,
                'BAYAR_BUNGA' => $paidNow,
                'DISKON_BUNGA' => max(0, $discount),
                'BAYAR_POKOK' => 0 ,
                'BAYAR_DENDA' => $bayarDenda ?? 0,
            ];
        }

        $semuaSudahLewat = true;
        foreach ($data as $row) {
            if ($row['PAYMENT_DATE'] >= $currentDate) {
                $semuaSudahLewat = false;
                break;
            }
        }

        if ($paymentPokok > 0) {
            $maxIndex = null;

            foreach ($data as $index => $row) {
                if ($maxIndex === null || $row['INSTALLMENT_COUNT'] > $data[$maxIndex]['INSTALLMENT_COUNT']) {
                    $maxIndex = $index;
                }
            }

            if ($semuaSudahLewat && $maxIndex !== null) {
                $data[$maxIndex]['INSTALLMENT'] = $res->PRINCIPAL + $totalInterest;
                $data[$maxIndex]['BAYAR_POKOK'] = $paymentPokok;
            } else {
                // Jalankan proses biasa
                $currentMonth = date('m');
                $currentYear = date('Y');
                $currentPaymentIndex = null;

                foreach ($data as $index => $row) {
                    $rowMonth = date('m', strtotime($row['PAYMENT_DATE']));
                    $rowYear = date('Y', strtotime($row['PAYMENT_DATE']));

                    if ($rowMonth == $currentMonth && $rowYear == $currentYear && $currentPaymentIndex === null) {
                        $currentPaymentIndex = $index;
                    }
                }

                if ($currentPaymentIndex === null && count($data) > 0) {
                    $currentPaymentIndex = 0;
                }

                if ($currentPaymentIndex !== null) {
                    $data[$currentPaymentIndex]['PRINCIPAL'] += $paymentPokok;
                    // $data[$currentPaymentIndex]['INSTALLMENT'] += $paymentPokok;
                    $data[$currentPaymentIndex]['BAYAR_POKOK'] = $paymentPokok;
                }

                $calc = 0;
                if ($maxIndex !== null) {
                    $data[$maxIndex]['PRINCIPAL'] -= $paymentPokok;
                    $sisaPokok = floatval($data[$maxIndex]['PRINCIPAL']);
                    $value = round($sisaPokok * (3 / 100), 2);

                    $calc = ceil($value / 1000) * 1000;
                }

                $sisaPaymentBunga = $paymentBunga;
                $minCount = isset($currentPaymentIndex) ? $data[$currentPaymentIndex]['INSTALLMENT_COUNT'] : null;

                foreach ($data as $index => $row) {
                    if (isset($creditSchedule[$index]) && strtoupper($creditSchedule[$index]->PAID_FLAG) === 'PAID') {
                        continue;
                    }

                    $paymentValueInterest = floatval($creditSchedule[$index]->PAYMENT_VALUE_INTEREST ?? 0);

                    if (
                        $minCount !== null &&
                        $row['INSTALLMENT_COUNT'] > $minCount &&
                        $paymentValueInterest == 0 &&
                        floatval($row['BAYAR_BUNGA']) == 0
                    ) {
                        $data[$index]['INTEREST'] = $calc;
                        $data[$index]['INSTALLMENT'] = $calc + $data[$index]['PRINCIPAL'];
                    }

                    $maxBayarBunga = min($data[$index]['INTEREST'], $row['INTEREST']);
                    $paidNow = min($sisaPaymentBunga, $maxBayarBunga);
                    $sisaPaymentBunga -= $paidNow;

                    $data[$index]['BAYAR_BUNGA'] = $paidNow;
                    $data[$index]['DISKON_BUNGA'] = max(0, $data[$index]['INTEREST'] - $paidNow);
                }
            }
        }

        return $data;
    }

    public function processPokokBungaMenurun($request, $kwitansiDetail)
    {
        $loanNumber = $request->LOAN_NUMBER ?? $kwitansiDetail->LOAN_NUMBER;
        $noTransaksi = $kwitansiDetail->NO_TRANSAKSI;

        $kwitansi = $this->getKwitansi($loanNumber, $noTransaksi);

        if (!$kwitansi) return;

        $credit = M_Credit::with(['arrears'])->where('LOAN_NUMBER', $loanNumber)->first();

        $details = collect($kwitansi->kwitansi_pelunasan_detail);

        foreach ($details as $detail) {
            $this->processDetail($request, $loanNumber, $detail, $kwitansi);
        }

        if($request->FLAG_DISKON == true){
            $this->updateCreditStatusFlagDiskon($request, $credit);
        }else{
            $this->updateCreditStatus($request, $credit, $loanNumber, $details);
        }
    }

    private function getKwitansi($loanNumber, $noTransaksi)
    {
        return M_Kwitansi::with(['kwitansi_pelunasan_detail', 'branch:ID,CODE,CODE_NUMBER'])
            ->select('LOAN_NUMBER', 'METODE_PEMBAYARAN', 'BRANCH_CODE', 'NO_TRANSAKSI', 'CREATED_BY')
            ->where('LOAN_NUMBER', $loanNumber)
            ->where('NO_TRANSAKSI', $noTransaksi)
            ->first();
    }

    private function processDetail($request, $loanNumber, $detail, $kwitansi)
    {
        $schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loanNumber,
            'INSTALLMENT_COUNT' => $detail['angsuran_ke'],
            'PAYMENT_DATE' => $detail['tgl_angsuran'],
        ])->first();

        if (!$schedule) {
            throw new Exception("Credit schedule not found for angsuran ke-{$detail['angsuran_ke']}", 1);
        }

        $principalDetail     = floatval($detail['principal']);
        $interestDetail      = floatval($detail['interest']);
        $paidPrincipal       = floatval($detail['bayar_pokok']);
        $paidInterest        = floatval($detail['bayar_bunga']);
        $installmentValue    = floatval($detail['installment']);
        $paidDiskonPrincipal       = floatval($detail['diskon_pokok']);
        $paidDiskonInterest        = floatval($detail['diskon_bunga']);

        $beforePaidPrincipal = floatval($schedule->PAYMENT_VALUE_PRINCIPAL);
        $beforePaidInterest  = floatval($schedule->PAYMENT_VALUE_INTEREST);
        $paymentValue        = floatval($schedule->PAYMENT_VALUE);

        $totalInterest       = $beforePaidInterest + $paidInterest;
        $totalPrincipal      = $beforePaidPrincipal + $paidPrincipal;
        $totalPaid           = $paidPrincipal + $paidInterest + $paidDiskonPrincipal + $paidDiskonInterest;

        $currentDate = date('Y-m-d');
        $isPastDue   = strtotime($schedule->PAYMENT_DATE) < strtotime($currentDate);

        $fields = [
            'PRINCIPAL'               => $principalDetail,
            'INTEREST'                => $interestDetail,
            'INSTALLMENT'             => $installmentValue,
            'PAYMENT_VALUE_PRINCIPAL' => $totalPrincipal,
            'PAYMENT_VALUE_INTEREST'  => $totalInterest,
        ];

        if (!$isPastDue) {
            $fields['PRINCIPAL_REMAINS'] = $principalDetail;
        }

        $schedule->update($fields);

        $getArrears = M_Arrears::where([
            'LOAN_NUMBER' => $loanNumber,
            'START_DATE' => $detail['tgl_angsuran'],
            'STATUS_REC' => 'A',
        ])->orderBy('START_DATE', 'ASC')->first();

        if ($getArrears) {
            $ttlPrincipal = floatval($getArrears->PAID_PCPL) + floatval($detail['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($getArrears->PAID_INT) + floatval($detail['bayar_bunga'] ?? 0);
            $ttlPenalty = floatval($getArrears->PAID_PENALTY) + floatval($detail['bayar_denda'] ?? 0);
            $ttlDiscPrincipal = floatval($getArrears->WOFF_PCPL) + floatval($detail['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($getArrears->WOFF_INT) + floatval($detail['diskon_bunga'] ?? 0);
            $ttlDiscPenalty = floatval($getArrears->WOFF_PENALTY) + floatval($detail['diskon_denda'] ?? 0);

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

        $insufficient = ($totalPrincipal + $totalInterest) - $installmentValue;

        if ($totalPaid > 0 || $insufficient == 0) {
            $schedule->update([
                'INSUFFICIENT_PAYMENT' => $insufficient,
                'PAYMENT_VALUE'        => $paymentValue + $totalPaid,
                'PAID_FLAG'            => $insufficient == 0 ? "PAID" : "",
            ]);

            $this->addPayment($request, $kwitansi, $detail);
        }
    }

    private function updateCreditStatus($request, $credit, $loanNumber, $details)
    {
        $total_bayar_pokok = $request->BAYAR_POKOK ?: $details->sum(fn($item) => (float) $item['bayar_pokok']);
        $total_bayar_bunga = $request->BAYAR_BUNGA ?: $details->sum(fn($item) => (float) $item['bayar_bunga']);
        $total_bayar_denda = $request->BAYAR_DENDA ?: $details->sum(fn($item) => (float) $item['bayar_denda']);

        $allPaid = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)
            ->where(
                fn($query) =>
                $query->where('PAID_FLAG', '!=', 'PAID')
                    ->orWhereNull('PAID_FLAG')
            )->count();

        $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->sum('INTEREST');

        $totalOriginal = $credit->PCPL_ORI + $credit->INTRST_ORI;
        $totalPaid = $credit->PAID_PRINCIPAL + $credit->PAID_INTEREST;

        $credit->update([
            'INTRST_ORI' => $totalInterest ?? 0,
            'STATUS_REC' => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'PT' : $credit->STATUS_REC,
            'STATUS'     => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'D'  : $credit->STATUS,
            'PAID_PRINCIPAL' => $credit->PAID_PRINCIPAL + $total_bayar_pokok,
            'PAID_INTEREST'  => $credit->PAID_INTEREST + $total_bayar_bunga,
            'PAID_PENALTY'   => $credit->PAID_PENALTY + $total_bayar_denda,
        ]);
    }

    private function updateCreditStatusFlagDiskon($request, $credit)
    {
        $credit->update([
            'STATUS_REC' => !empty($request->DISKON_POKOK) && floatval($request->DISKON_POKOK) > 0 ? 'BL' : 'PT',
            'STATUS'     => 'D',
            'DISCOUNT_PRINCIPAL' => floatval($request->DISKON_POKOK),
            'DISCOUNT_INTEREST' => floatval($request->DISKON_POKOK),
        ]);
    }

    // private function updateCreditStatus($request, $credit, $loanNumber)
    // {
    //     $allPaid = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)
    //         ->where(function ($query) {
    //             $query->where('PAID_FLAG', '!=', 'PAID')
    //                 ->orWhereNull('PAID_FLAG');
    //         })
    //         ->count();

    //     $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loanNumber)->sum('INTEREST');

    //     $totalOriginal = $credit->PCPL_ORI + $credit->INTRST_ORI;
    //     $totalPaid = $credit->PAID_PRINCIPAL + $credit->PAID_INTEREST;

    //     $credit->update([
    //         'INTRST_ORI' => $totalInterest ?? 0,
    //         'STATUS_REC' => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'PT' : $credit->STATUS_REC,
    //         'STATUS'     => ($totalOriginal == $totalPaid && $credit->arrears->isEmpty() || $allPaid == 0) ? 'D'  : $credit->STATUS,
    //         'PAID_PRINCIPAL' => $credit->PAID_PRINCIPAL + $request->BAYAR_POKOK,
    //         'PAID_INTEREST' => $credit->PAID_INTEREST + $request->BAYAR_BUNGA,
    //         'PAID_PENALTY' => $credit->PAID_PENALTY + $request->BAYAR_DENDA,
    //     ]);
    // }

    public function addPayment($request, $kwitansi, $data)
    {
        $uid = Uuid::uuid7()->toString();
        $loanNumber = $request->LOAN_NUMBER ?? $kwitansi->LOAN_NUMBER;
        $bayarPokok = floatval($data['bayar_pokok'] ?? 0);
        $bayarBunga = floatval($data['bayar_bunga'] ?? 0);
        $bayarDenda = floatval($data['bayar_denda'] ?? 0);
        $totalPayment = $bayarPokok + $bayarBunga + $bayarDenda;

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => 'angsuran',
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
            'ORIGINAL_AMOUNT' => $totalPayment ?? 0,
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $data['tgl_angsuran'] ?? null,
            'END_DATE' => now(),
            'USER_ID' => $kwitansi->CREATED_BY ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? ''
        ];

        if (!M_Payment::where($paymentData)->first()) {
            M_Payment::create($paymentData);
        }

        if ($bayarPokok != 0) {
            M_PaymentDetail::create([
                'PAYMENT_ID' => $uid,
                'ACC_KEYS' => 'ANGSURAN_POKOK',
                'ORIGINAL_AMOUNT' => $bayarPokok
            ]);
        }

        if ($bayarBunga != 0) {
            M_PaymentDetail::create([
                'PAYMENT_ID' => $uid,
                'ACC_KEYS' => 'ANGSURAN_BUNGA',
                'ORIGINAL_AMOUNT' => $bayarBunga
            ]);
        }

        if ($bayarDenda != 0) {
            M_PaymentDetail::create([
                'PAYMENT_ID' => $uid,
                'ACC_KEYS' => 'BAYAR_DENDA',
                'ORIGINAL_AMOUNT' => $bayarDenda
            ]);
        }
    }

    public function cancel($loan_number, $no_inv)
    {
        // M_Kwitansi::where('LOAN_NUMBER', $loan_number)
        //     ->where('NO_TRANSAKSI', '>=', $no_inv)
        //     ->update(['STTS_PAYMENT' => 'CANCEL']);
        M_Kwitansi::where('LOAN_NUMBER', $loan_number)
            ->whereRaw(
                'CAST(RIGHT(NO_TRANSAKSI, 9) AS UNSIGNED) >= CAST(RIGHT(?, 9) AS UNSIGNED)',
                [$no_inv]
            )
            ->update(['STTS_PAYMENT' => 'CANCEL']);


        // M_Payment::where('LOAN_NUM', $loan_number)
        //     ->where('INVOICE', '>=', $no_inv)
        //     ->update(['STTS_RCRD' => 'CANCEL']);

        M_Payment::where('LOAN_NUM', $loan_number)
            ->whereRaw(
                'CAST(RIGHT(INVOICE, 9) AS UNSIGNED) >= CAST(RIGHT(?, 9) AS UNSIGNED)',
                [$no_inv]
            )
            ->update(['STTS_RCRD' => 'CANCEL']);


        $scheduleBefore = $this->s_creditScheduleBefore->getDataCreditSchedule($loan_number, $no_inv);
        $arrearsBefore = $this->s_arrearsBefore->getDataArrears($no_inv);
        $creditBefore = $this->s_creditBefore->getDataCredit($loan_number, $no_inv);

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

        M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->delete();
        foreach ($scheduleBefore as $value) {
            $fields = [
                'LOAN_NUMBER' => $value['LOAN_NUMBER'],
                'INSTALLMENT_COUNT' => $value['INSTALLMENT_COUNT'],
                'PAYMENT_DATE' => $value['PAYMENT_DATE'],
                'PRINCIPAL' => $value['PRINCIPAL'] ?? 0,
                'INTEREST' => $value['INTEREST'] ?? 0,
                'INSTALLMENT' => $value['INSTALLMENT'] ?? 0,
                'PRINCIPAL_REMAINS' => $value['PRINCIPAL_REMAINS'] ?? 0,
                'PAYMENT_VALUE_PRINCIPAL' => $value['PAYMENT_VALUE_PRINCIPAL'] ?? 0,
                'PAYMENT_VALUE_INTEREST' => $value['PAYMENT_VALUE_INTEREST'] ?? 0,
                'DISCOUNT_PRINCIPAL' => $value['DISCOUNT_PRINCIPAL'] ?? 0,
                'DISCOUNT_INTEREST' => $value['DISCOUNT_INTEREST'] ?? 0,
                'INSUFFICIENT_PAYMENT' => $value['INSUFFICIENT_PAYMENT'] ?? 0,
                'PAYMENT_VALUE' => $value['PAYMENT_VALUE'] ?? 0,
                'PAID_FLAG' => $value['PAID_FLAG']
            ];

            M_CreditSchedule::create($fields);
        }

        M_Arrears::where('LOAN_NUMBER', $loan_number)->delete();
        foreach ($arrearsBefore as $details) {
            $fields = [
                'STATUS_REC' => $details['STATUS_REC'],
                'LOAN_NUMBER' => $details['LOAN_NUMBER'],
                'START_DATE' => $details['START_DATE'],
                'END_DATE' => $details['END_DATE'],
                'PAST_DUE_PCPL' => $details['PAST_DUE_PCPL'],
                'PAST_DUE_INTRST' => $details['PAST_DUE_INTRST'],
                'PAST_DUE_PENALTY' => $details['PAST_DUE_PENALTY'],
                'PAID_PCPL' => $details['PAID_PCPL'],
                'PAID_INT' => $details['PAID_INT'],
                'PAID_PENALTY' => $details['PAID_PENALTY'],
                'WOFF_PCPL' => $details['WOFF_PCPL'],
                'WOFF_INT' => $details['WOFF_INT'],
                'WOFF_PENALTY' => $details['WOFF_PENALTY'],
                'PENALTY_RATE' => $details['PENALTY_RATE'],
                'TRNS_CODE' => $details['TRNS_CODE'],
                'CREATED_AT' => $details['CREATED_AT'],
                'UPDATED_AT' => $details['UPDATED_AT'],
            ];

            M_Arrears::create($fields);
        }
    }
}

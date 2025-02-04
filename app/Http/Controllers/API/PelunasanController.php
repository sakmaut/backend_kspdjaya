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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PelunasanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_Kwitansi::where('PAYMENT_TYPE','pelunasan')->get();

            $dto = R_Pelunasan::collection($data);

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

            $result = DB::select(
                "select	(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as SISA_POKOK,
                        c.BUNGA as BUNGA_BERJALAN,
                        b.INT_ARR as TUNGGAKAN_BUNGA,
                        b.TUNGGAKAN_DENDA as TUNGGAKAN_DENDA,
                        b.DENDA_TOTAL as DENDA,
                        (coalesce(a.PENALTY_RATE,7.5)/100)*(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as PINALTI,
                        d.DISC_BUNGA
                from	credit a
                        left join (	select	LOAN_NUMBER, 
                                            sum(coalesce(PAST_DUE_INTRST,0))-sum(coalesce(PAID_INT,0)) as INT_ARR, 
                                            sum(case when STATUS_REC <> 'A' then coalesce(PAST_DUE_PENALTY,0) end)-
                                                sum(case when STATUS_REC <> 'A' then coalesce(PAID_PENALTY,0) end) as TUNGGAKAN_DENDA,
                                            sum(coalesce(PAST_DUE_PENALTY,0))-sum(coalesce(PAID_PENALTY,0)) as DENDA_TOTAL
                                    from	arrears
                                    where	LOAN_NUMBER = '{$loan_number}'
                                    group 	by LOAN_NUMBER) b
                            on b.LOAN_NUMBER = a.LOAN_NUMBER
                        left join (	select	LOAN_NUMBER, 
                                            INTEREST * datediff(now(), PAYMENT_DATE) / 
                                                date_format(date_add(date_add(str_to_date(concat('01',date_format(PAYMENT_DATE,'%m%Y')),'%d%m%Y'),interval 1 month),interval -1 day),'%m') as BUNGA
                                    from	credit_schedule
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and PAYMENT_DATE = (	select	max(PAYMENT_DATE)
                                                                    from	credit_schedule
                                                                    where	LOAN_NUMBER = '{$loan_number}'
                                                                            and PAYMENT_DATE <= now())) c
                            on c.LOAN_NUMBER = a.LOAN_NUMBER
                        left join (	select	LOAN_NUMBER, INTEREST-PAYMENT_VALUE_INTEREST as DISC_BUNGA
                                    from	credit_schedule
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and PAYMENT_DATE = (	select	max(PAYMENT_DATE)
                                                                    from	credit_schedule
                                                                    where	LOAN_NUMBER = '{$loan_number}'
                                                                            and PAYMENT_DATE>now())) d
                            on d.LOAN_NUMBER = a.LOAN_NUMBER
                where a.LOAN_NUMBER = '{$loan_number}'"
            );

            $query2 = DB::select("
                    select	sum(INTEREST-coalesce(PAYMENT_VALUE_INTEREST,0)) as DISC_BUNGA
					from	credit_schedule
					where	LOAN_NUMBER = '{$loan_number}'
							and PAYMENT_DATE>now()
            ");

            $processedResults = array_map(function ($item) {
                return [
                    'SISA_POKOK' => round(floatval($item->SISA_POKOK), 2),
                    'BUNGA_BERJALAN' => round(floatval($item->BUNGA_BERJALAN), 2),
                    'TUNGGAKAN_BUNGA' => round(floatval($item->TUNGGAKAN_BUNGA), 2),
                    'TUNGGAKAN_DENDA' => round(floatval($item->TUNGGAKAN_DENDA), 2),
                    'DENDA' => round(floatval($item->DENDA), 2),
                    'PINALTI' => round(floatval($item->PINALTI), 2),
                ];
            }, $result);

            $discBunga = 0;
            if (!empty($query2) && isset($query2[0]->DISC_BUNGA)) {
                $discBunga = round(floatval($query2[0]->DISC_BUNGA), 2);
            }
        
            foreach ($processedResults as &$processedResult) {
                $processedResult['DISC_BUNGA'] = $discBunga;
            }
        
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

            $loan_number = $request->LOAN_NUMBER;
            
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');
    
            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();
            
            $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            $status = "PAID";

            if (array_sum($discounts) > 0 || strtolower($request->METODE_PEMBAYARAN) === 'transfer') {
                $status = "PENDING";
            }

            if (!M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->exists()) {

                $this->saveKwitansi($request, $detail_customer, $no_inv, $status);
                $this->proccessKwitansiDetail($request, $loan_number, $no_inv);
            }

            if ($status === "PAID") {
                $this->proccess($request, $loan_number, $no_inv, $status);
            }else{

                $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
                                            ->where(function ($query) {
                                                $query->where('PAID_FLAG', '!=', 'PAID')->orWhereNull('PAID_FLAG');
                                            })
                                            ->orderBy('PAYMENT_DATE', 'asc')
                                            ->get();

                foreach ($creditSchedule as $res) {

                    $res->update(['PAID_FLAG' => 'PENDING']);

                    M_Arrears::where([
                        'LOAN_NUMBER' => $res['LOAN_NUMBER'],
                        'START_DATE' => $res['PAYMENT_DATE'],
                        'STATUS_REC' => 'A'
                    ])->update(['STATUS_REC' => 'PENDING']);
                }               
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

        if(!empty($pelunasanKwitansiDetail)){
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

                $this->updateCreditSchedule($loan_number,$res);
                $this->updateArrears($loan_number, $res);
            }

            $this->updateCredit($request,$loan_number);
        }
    }

    function proccessCancel($request, $loan_number, $no_inv, $status)
    {
        $pelunasanKwitansiDetail = M_KwitansiDetailPelunasan::where(['no_invoice' => $no_inv, 'loan_number' => $loan_number])->get();

        $kwitansi = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if($kwitansi){
            $kwitansi->update(['STTS_PAYMENT' => $status]);
        }

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

                $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])->first();

                if ($getCreditSchedule) {
                    $getCreditSchedule->update([
                        'PAID_FLAG' => ''
                    ]);
                }

                $getArrears = M_Arrears::where([
                    'LOAN_NUMBER' => $loan_number,
                    'START_DATE' => $res['tgl_angsuran'],
                ])->first();

                if ($getArrears) {
                    $getArrears->update([
                        'STATUS_REC' => 'A',
                        'UPDATED_AT' => Carbon::now(),
                    ]);
                }
            }
        }
    }

    function proccessPayment($request,$uid,$no_inv,$status, $res){
        $originalAmount = (
            ($res['bayar_pokok'] ?? 0) + 
            ($res['bayar_bunga'] ?? 0) + 
            ($res['bayar_denda'] ?? 0) + 
            ($res['diskon_pokok'] ?? 0) + 
            ($res['diskon_bunga'] ?? 0) + 
            ($res['diskon_denda'] ?? 0)
        );
        
        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Pelunasan Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::find($request->user()->branch_id)->CODE_NUMBER ?? '',
            'LOAN_NUM' => $res['loan_number'] ?? '',
            'ENTRY_DATE' => Carbon::now(),
            'TITLE' => 'Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'ORIGINAL_AMOUNT' => $originalAmount,
            'START_DATE' => $res['tgl_angsuran'] ?? '',
            'END_DATE' => Carbon::now(),
            'USER_ID' => $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => Carbon::now()
        ]);        
    }

    function updateCreditSchedule($loan_number,$res){
    
        $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])->first();

        if($getCreditSchedule){

            $valBeforePrincipal = $getCreditSchedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $getCreditSchedule->PAYMENT_VALUE_INTEREST;

            $ttlPrincipal = floatval($valBeforePrincipal) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($valBeforeInterest) + floatval($res['bayar_pokok'] ?? 0);

            $getCreditSchedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $ttlPrincipal,
                'PAYMENT_VALUE_INTEREST' => $ttlInterest, 
                'DISCOUNT_PRINCIPAL' => $res['diskon_pokok']??0, 
                'DISCOUNT_INTEREST' => $res['diskon_bunga'] ?? 0, 
                'PAYMENT_VALUE' => $res['installment'] ?? 0,
                'PAID_FLAG' => 'PAID'
            ]);
        }
    }

    function updateArrears($loan_number, $res)
    {
        $getArrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $res['tgl_angsuran'],
        ])->first();

        if ($getArrears) {
            $ttlPrincipal = floatval($getArrears->PAID_PCPL) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($getArrears->PAID_INT) + floatval($res['bayar_bunga'] ?? 0);
            $ttlPenalty = floatval($getArrears->PAID_PENALTY) + floatval($res['bayar_denda'] ?? 0);

            $checkDiscountArrears = empty($res['diskon_pokok']) && empty($res['diskon_bunga']) && empty($res['diskon_denda']);

            $getArrears->update([
                'END_DATE' => Carbon::now()->format('Y-m-d'),
                'PAID_PCPL' => $ttlPrincipal,
                'PAID_INT' => $ttlInterest,
                'PAID_PENALTY' => $ttlPenalty,
                'WOFF_PCPL' => $res['diskon_pokok'] ?? 0,
                'WOFF_INT' => $res['diskon_bunga'] ?? 0,
                'WOFF_PENALTY' => $res['diskon_denda'] ?? 0,
                'STATUS_REC' => $checkDiscountArrears ? 'S' : 'D',
                'UPDATED_AT' => Carbon::now(),
            ]);
        }
    }

    function updateCredit($request, $loan_number)
    {

        $bayarPokok = $request->BAYAR_POKOK;
        $bayarDiscountPokok = $request->DISKON_POKOK;
        $bayarBunga = $request->BAYAR_BUNGA;
        $bayarDiscountBunga = $request->DISKON_BUNGA;
        $bayarDenda = $request->BAYAR_DENDA;
        $bayarDiscountDenda = $request->DISKON_DENDA;

        $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

        if ($credit) {
            $credit->update([
                'PAID_PRINCIPAL' => floatval($credit->PAID_PRINCIPAL) + floatval($bayarPokok),
                'PAID_INTEREST' => floatval($credit->PAID_INTEREST) + floatval($bayarBunga),
                'DISCOUNT_PRINCIPAL' => floatval($credit->DISCOUNT_PRINCIPAL) + floatval($bayarDiscountPokok),
                'DISCOUNT_INTEREST' => floatval($credit->DISCOUNT_INTEREST) + floatval($bayarDiscountBunga),
                'PAID_PENALTY' => floatval($credit->PAID_PENALTY) + floatval($bayarDenda),
                'DISCOUNT_PENALTY' => floatval($credit->DISCOUNT_PENALTY) + floatval($bayarDiscountDenda),
                'STATUS' => 'D',
                'END_DATE' => now()
            ]);
        }
    }
    
    private function saveKwitansi($request, $customer, $no_inv,$status)
    {
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
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR??0,
            "PINALTY_PELUNASAN" => $request->BAYAR_PINALTI??0,
            "DISKON_PINALTY_PELUNASAN" => $request->DISKON_PINALTI??0,
            "PEMBULATAN" => $request->PEMBULATAN,
            "DISKON" => $request->PEMBULATAN,
            "KEMBALIAN" => $request->KEMBALIAN,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->fullname
        ];
    
        M_Kwitansi::create($data);
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

    function preparePaymentData($payment_id,$acc_key, $amount)
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
                                            ->select(   'a.LOAN_NUMBER',
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
                                                        'a.PAID_FLAG')
                                            ->get();

        $this->principalCalculate($request, $loan_number, $no_inv, $creditSchedules);
        $this->interestCalculate($request, $loan_number, $no_inv, $creditSchedules);
        $arrears = M_Arrears::where(['LOAN_NUMBER' => $loan_number, 'STATUS_REC' => 'A'])->get();
        $this->arrearsCalculate($request, $loan_number, $no_inv,$arrears);
    }

    private function principalCalculate($request, $loan_number, $no_inv, $creditSchedule)
    {
        $this->calculatePayment(
            $request->BAYAR_POKOK,
            $request->DISKON_POKOK,
            $creditSchedule,
            'PRINCIPAL',
            'PAYMENT_VALUE_PRINCIPAL',
            'BAYAR_POKOK',
            'DISKON_POKOK',
            $loan_number,
            $no_inv
        );
    }

    private function interestCalculate($request, $loan_number, $no_inv, $creditSchedule)
    {
        $this->calculatePayment(
            $request->BAYAR_BUNGA,
            $request->DISKON_BUNGA,
            $creditSchedule,
            'INTEREST',
            'PAYMENT_VALUE_INTEREST',
            'BAYAR_BUNGA',
            'DISKON_BUNGA',
            $loan_number,
            $no_inv
        );
    }

    private function arrearsCalculate($request, $loan_number, $no_inv, $arrears)
    {
        $this->calculatePayment(
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

    private function calculatePayment($paymentAmount, $discountAmount, $schedule, $fieldKey, $valueKey, $paymentParam, $discountParam, $loan_number, $no_inv)
    {
        $remainingPayment = $paymentAmount;
        $remainingDiscount = $discountAmount;

        foreach ($schedule as $res) {
            // Get the current value of the field and payment status
            $valBefore = $res->{$valueKey};
            $getAmount = $res->{$fieldKey};

            // Proceed only if there's an amount to pay
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

                $param[$paymentParam] = ($remainingPayment < $remainingToPay) ? $newPaymentValue : $remainingPayment;
                $this->insertKwitansiDetail($loan_number, $no_inv, $res, $param);

                // If there's a discount to apply
                if ($remainingDiscount > 0) {
                    $remainingToDiscount = $getAmount - $newPaymentValue;

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

        $checkDetail = M_KwitansiDetailPelunasan::where([
            'no_invoice' => $no_inv,
            'tgl_angsuran' => $tgl_angsuran,
        ])->first();

        $credit = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran,
        ])->first();

        if (!$checkDetail) {
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
        } else {
            $fields = ['BAYAR_POKOK', 'DISKON_POKOK', 'BAYAR_BUNGA', 'DISKON_BUNGA', 'BAYAR_DENDA', 'DISKON_DENDA'];

            foreach ($fields as $field) {
                if (isset($param[$field]) && $param[$field] != 0) {
                    $checkDetail->update([strtolower($field) => $param[$field]]);
                }
            }
        }
    }
}



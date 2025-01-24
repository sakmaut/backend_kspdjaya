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
            $uid = Uuid::uuid7()->toString();
            $request->merge(['payment_id' => $uid]);
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');
    
            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();
            
            $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            $status = "PAID";

            if (array_sum($discounts) > 0 || strtolower($request->METODE_PEMBAYARAN) === 'transfer') {
                $status = "PENDING";
            }
             
            // $this->saveKwitansi($request, $detail_customer, $no_inv, $status);
            
            // if ($status === "PAID") {
            //     $this->proccess($request, $loan_number, $uid, $no_inv, $status);
            // }

            // $data = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            // $dto = new R_KwitansiPelunasan($data);
            $this->proccess($request, $loan_number, $uid, $no_inv, $status);
            DB::commit();
            return response()->json('', 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    function proccess($request,$loan_number,$uid,$no_inv,$status){
        $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

        $getStartDate = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
                        ->where(function($query) {
                            $query->where('PAID_FLAG', '!=', 'PAID')->orWhereNull('PAID_FLAG');
                        })
                        ->orderBy('PAYMENT_DATE', 'asc')
                        ->first(); 
    
        // M_Payment::create([
        //     'ID' => $uid,
        //     'ACC_KEY' => 'pelunasan',
        //     'STTS_RCRD' => $status,
        //     'INVOICE' => $no_inv,
        //     'NO_TRX' => $request->uid,
        //     'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN,
        //     'BRANCH' => $getCodeBranch->CODE_NUMBER,
        //     'LOAN_NUM' => $request->LOAN_NUMBER,
        //     'START_DATE' => $getStartDate->PAYMENT_DATE??null,
        //     'ENTRY_DATE' => Carbon::now(),
        //     'TITLE' => 'pelunasan',
        //     'ORIGINAL_AMOUNT' => $request->TOTAL_BAYAR,
        //     'OS_AMOUNT' => 0,
        //     'AUTH_BY' => $request->user()->id,
        //     'AUTH_DATE' => Carbon::now()
        // ]);

        // $this->createPaymentDetails($request,$uid);
        $this->creditScheculeRepayment($request,$loan_number);
        // $this->arrearsRepayment($request,$loan_number);
    }

    function creditScheculeRepayment($request,$loan_number){
        $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
                            ->where(function($query) {
                                $query->where('PAID_FLAG', '!=', 'PAID')->orWhereNull('PAID_FLAG');
                            })->get();

        $bayarPokok = $request->BAYAR_POKOK;
        $bayarDiscountPokok = $request->DISKON_POKOK;
        $bayarBunga = $request->BAYAR_BUNGA;
        $bayarDiscountBunga = $request->DISKON_BUNGA;

        $remaining_discount = $bayarDiscountPokok; 
        $remaining_discount_bunga = $bayarDiscountBunga;

        foreach ($creditSchedule as $res) {
            $valBeforePrincipal = $res['PAYMENT_VALUE_PRINCIPAL'];
            $valBeforeInterest = $res['PAYMENT_VALUE_INTEREST'];
            $getPrincipal = $res['PRINCIPAL'];
            $getInterest = $res['INTEREST'];
            $getDiscountPrincipal = $res['DISCOUNT_PRINCIPAL'];
            $getDiscountInterest = $res['DISCOUNT_INTEREST'];
            $getInstallment = $res['INSTALLMENT'];

            $new_payment_value_principal = $valBeforePrincipal;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($bayarPokok >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $bayarPokok -= $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $bayarPokok;
                    $bayarPokok = 0;
                }

                $updates = [];
                if ($new_payment_value_principal !== $valBeforePrincipal) {
                    $updates[' '] = $new_payment_value_principal;
                }

                if ($remaining_discount > 0) {
                    $remaining_to_principal_for_discount = $getPrincipal - $new_payment_value_principal;


                    if ($remaining_discount >= $remaining_to_principal_for_discount) {
                        $updates['DISCOUNT_PRINCIPAL'] = $remaining_to_principal_for_discount;
                        $new_payment_value_principal += $remaining_to_principal_for_discount; 
                        $remaining_discount -= $remaining_to_principal_for_discount;
                    } else {
                        $updates['DISCOUNT_PRINCIPAL'] = $remaining_discount;
                        $new_payment_value_principal += $remaining_discount;
                        $remaining_discount = 0;
                    }
                }

                $total_payment_value_principal = $new_payment_value_principal;
                $total_discount_principal = $updates['DISCOUNT_PRINCIPAL'] ?? 0;

                $credit = M_Credit::where('LOAN_NUMBER',$loan_number)->first();

                if($credit){
                    $credit->update([
                        'PAYMENT_VALUE_PRINCIPAL' => $total_payment_value_principal, 
                        'DISCOUNT_PRINCIPAL' => $total_discount_principal, 
                    ]);
                }

                if ($valBeforeInterest < $getInterest) {
                    $remaining_to_interest = $getInterest - $valBeforeInterest;
                    $interestUpdates = $this->hitungBunga($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res);
                    $bayarBunga = $interestUpdates['bayarBunga'];
                    $remaining_discount_bunga = $interestUpdates['remaining_discount_bunga'];
                }                

                if (!empty($updates)) {
                    $res->update($updates);
                }
            }

            $sumAll = floatval($valBeforePrincipal) + floatval($valBeforeInterest) + floatval($getPrincipal) + floatval($getInterest) + floatval($getDiscountPrincipal) + floatval($getDiscountInterest);
            $checkPaid = floatval($getInstallment) == floatval($sumAll);
            $insufficient = floatval($getInstallment) == floatval($checkPaid);

            $res->update([
                'INSUFFICIENT_PAYMENT' => $insufficient ? 0 : $insufficient,
                'PAYMENT_VALUE' => $sumAll, 
                'PAID_FLAG' => $checkPaid? 'PAID':''
            ]);

            if ($remaining_discount <= 0) {
                break;
            }                
        }
    }

    function arrearsRepayment($request,$loan_number){

        $arrears = M_Arrears::where(['LOAN_NUMBER' => $loan_number, 'STATUS_REC' => 'A'])->get();

        $bayarDenda = $request->BAYAR_DENDA;
        $bayarDiscountDenda = $request->DISKON_DENDA;
        $bayarPokok = $request->BAYAR_POKOK;
        $bayarDiscountPokok = $request->DISKON_POKOK;
        $bayarBunga = $request->BAYAR_BUNGA;
        $bayarDiscountBunga = $request->DISKON_BUNGA;

        $remaining_discount = $bayarDiscountPokok; 
        $remaining_discount_bunga = $bayarDiscountBunga;
        $remaining_discount_denda = $bayarDiscountDenda;

        foreach ($arrears as $res) {
            $valBeforePrincipal = $res['PAID_PCPL'];
            $valBeforeInterest = $res['PAID_INT'];
            $getPrincipal = $res['PAST_DUE_PCPL'];
            $getInterest = $res['PAST_DUE_INTRST'];
            $valBeforePenalty = $res['PAID_PENALTY'];
            $getPenalty = $res['PAST_DUE_PENALTY'];

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_penalty = $valBeforePenalty;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                // If bayarPokok is enough to cover the remaining principal
                if ($bayarPokok >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $bayarPokok -= $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $bayarPokok;
                    $bayarPokok = 0;  // No more bayarPokok to apply
                }

                $updates = [];
                if ($new_payment_value_principal !== $valBeforePrincipal) {
                    $updates['PAID_PCPL'] = $new_payment_value_principal;
                }

                if ($remaining_discount > 0) {
                    $remaining_to_principal_for_discount = $getPrincipal - $new_payment_value_principal;

                    // If the remaining discount can cover the remaining principal
                    if ($remaining_discount >= $remaining_to_principal_for_discount) {
                        $updates['WOFF_PCPL'] = $remaining_to_principal_for_discount;
                        $new_payment_value_principal += $remaining_to_principal_for_discount; // Apply discount
                        $remaining_discount -= $remaining_to_principal_for_discount; // Reduce the discount
                    } else {
                    // If the discount can't fully cover the remaining principal
                        $updates['WOFF_PCPL'] = $remaining_discount;
                        $new_payment_value_principal += $remaining_discount; // Apply discount
                        $remaining_discount = 0; // No more discount left
                    }
                }

                if ($valBeforeInterest < $getInterest) {
                    $remaining_to_interest = $getInterest - $valBeforeInterest;
                    $interestUpdates = $this->hitungBungaDenda($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res);
                    $bayarBunga = $interestUpdates['bayarBunga']; // Update the remaining bayarBunga
                    $remaining_discount_bunga = $interestUpdates['remaining_discount_bunga']; // Update the remaining discount for bunga
                }

                if ($valBeforePenalty < $getPenalty) {
                    $remaining_to_penalty = $getPenalty - $valBeforePenalty;
        
                    // If bayarDenda is enough to cover the remaining penalty
                    if ($bayarDenda >= $remaining_to_penalty) {
                        $new_payment_value_penalty = $getPenalty;
                        $bayarDenda -= $remaining_to_penalty;
                    } else {
                        $new_payment_value_penalty += $bayarDenda;
                        $bayarDenda = 0;  // No more bayarDenda to apply
                    }
        
                    if ($new_payment_value_penalty !== $valBeforePenalty) {
                        $updates['PAID_PENALTY'] = $new_payment_value_penalty;
                    }
        
                    // Apply discount to denda
                    if ($remaining_discount_denda > 0) {
                        $remaining_to_penalty_for_discount = $getPenalty - $new_payment_value_penalty;
        
                        // If the remaining discount can cover the remaining penalty
                        if ($remaining_discount_denda >= $remaining_to_penalty_for_discount) {
                            $updates['WOFF_PENALTY'] = $remaining_to_penalty_for_discount;
                            $new_payment_value_penalty += $remaining_to_penalty_for_discount; // Apply discount
                            $remaining_discount_denda -= $remaining_to_penalty_for_discount; // Reduce the discount
                        } else {
                            // If the discount can't fully cover the remaining penalty
                            $updates['WOFF_PENALTY'] = $remaining_discount_denda;
                            $new_payment_value_penalty += $remaining_discount_denda; // Apply discount
                            $remaining_discount_denda = 0; // No more discount left
                        }
                    }
                }

                if (!empty($updates)) {
                    $res->update($updates);
                }
            }   

            $checkStatus = floatval($getPrincipal) == floatval($valBeforePrincipal) ;
            $res->update(['END_DATE' =>now(),'STATUS_REC' => $checkStatus ? 'S' :'D']);
            if ($remaining_discount <= 0) {
                break;
            }                
        }
    }

    function hitungBunga($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res)
    {
        $new_payment_value_interest = $res['PAYMENT_VALUE_INTEREST'];
        $interestUpdates = [];

        // If bayarBunga is enough to cover the remaining interest
        if ($bayarBunga >= $remaining_to_interest) {
            $new_payment_value_interest = $res['INTEREST'];
            $bayarBunga -= $remaining_to_interest;
        } else {
            $new_payment_value_interest += $bayarBunga;
            $bayarBunga = 0;  // No more bayarBunga to apply
        }

        if ($new_payment_value_interest !== $res['PAYMENT_VALUE_INTEREST']) {
            $interestUpdates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;
        }

        // Apply discount to bunga
        if ($remaining_discount_bunga > 0) {
            $remaining_to_interest_for_discount = $res['INTEREST'] - $new_payment_value_interest;

            // If the remaining discount can cover the remaining interest
            if ($remaining_discount_bunga >= $remaining_to_interest_for_discount) {
                $interestUpdates['DISCOUNT_INTEREST'] = $remaining_to_interest_for_discount;
                $new_payment_value_interest += $remaining_to_interest_for_discount; // Apply discount
                $remaining_discount_bunga -= $remaining_to_interest_for_discount; // Reduce the discount
            } else {
                // If the discount can't fully cover the remaining interest
                $interestUpdates['DISCOUNT_INTEREST'] = $remaining_discount_bunga;
                $new_payment_value_interest += $remaining_discount_bunga; // Apply discount
                $remaining_discount_bunga = 0; // No more discount left
            }
        }

        // Update the record if there are any changes
        if (!empty($interestUpdates)) {
            $res->update($interestUpdates);
        }

        return [
            'bayarBunga' => $bayarBunga,
            'remaining_discount_bunga' => $remaining_discount_bunga
        ];
    }

    function hitungBungaDenda($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res)
    {
        $new_payment_value_interest = $res['PAID_INT'];
        $interestUpdates = [];

        // If bayarBunga is enough to cover the remaining interest
        if ($bayarBunga >= $remaining_to_interest) {
            $new_payment_value_interest = $res['PAST_DUE_INTRST'];
            $bayarBunga -= $remaining_to_interest;
        } else {
            $new_payment_value_interest += $bayarBunga;
            $bayarBunga = 0;  // No more bayarBunga to apply
        }

        if ($new_payment_value_interest !== $res['PAID_INT']) {
            $interestUpdates['PAID_INT'] = $new_payment_value_interest;
        }

        // Apply discount to bunga
        if ($remaining_discount_bunga > 0) {
            $remaining_to_interest_for_discount = $res['PAST_DUE_INTRST'] - $new_payment_value_interest;

            // If the remaining discount can cover the remaining interest
            if ($remaining_discount_bunga >= $remaining_to_interest_for_discount) {
                $interestUpdates['WOFF_INT'] = $remaining_to_interest_for_discount;
                $new_payment_value_interest += $remaining_to_interest_for_discount; // Apply discount
                $remaining_discount_bunga -= $remaining_to_interest_for_discount; // Reduce the discount
            } else {
                // If the discount can't fully cover the remaining interest
                $interestUpdates['WOFF_INT'] = $remaining_discount_bunga;
                $new_payment_value_interest += $remaining_discount_bunga; // Apply discount
                $remaining_discount_bunga = 0; // No more discount left
            }
        }

        // Update the record if there are any changes
        if (!empty($interestUpdates)) {
            $res->update($interestUpdates);
        }

        return [
            'bayarBunga' => $bayarBunga,
            'remaining_discount_bunga' => $remaining_discount_bunga
        ];
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
            'CUST_CODE' => $customer->CUST_CODE,
            'BRANCH_CODE' => $request->user()->branch_id,
            'NAMA' => $customer->NAME,
            'ALAMAT' => $customer->ADDRESS,
            'RT' => $customer->RT,
            'RW' => $customer->RW,
            'PROVINSI' => $customer->PROVINCE,
            'KOTA' => $customer->CITY,
            'KELUMATAN' => $customer->KECAMATAN,
            "METODRAHAN' => $customer->KELURAHAN,
            'KECAE_PEMBAYARAN" => $request->METODE_PEMBAYARAN,
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR,
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

    function createPaymentDetails($request,$uid){
        $payments = [
            'BAYAR_POKOK' => 'BAYAR PELUNASAN POKOK',
            'BAYAR_BUNGA' => 'BAYAR PELUNASAN BUNGA',
            'BAYAR_PINALTI' => 'BAYAR PELUNASAN PINALTY',
            'BAYAR_DENDA' => 'BAYAR PELUNASAN DENDA'
        ];
    
        foreach ($payments as $key => $description) {
            if ($request->$key != 0) {
                $data = $this->preparePaymentData($uid, $description, $request->$key);
                M_PaymentDetail::create($data);
            }
        }
    
        $discounts = [
            'DISKON_POKOK' => 'DISKON POKOK',
            'DISKON_BUNGA' => 'DISKON BUNGA',
            'DISKON_PINALTI' => 'DISKON PINALTY',
            'DISKON_DENDA' => 'DISKON DENDA'
        ];
    
        foreach ($discounts as $key => $description) {
            if ($request->$key != 0) {
                $data = $this->preparePaymentData($uid, $description, $request->$key);
                M_PaymentDetail::create($data);
            }
        } 
    }

    function preparePaymentData($payment_id,$acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    function updateCredit($loan_number,$key,$value)
    {

        $credit = M_Credit::where('LOAN_NUMBER',$loan_number)->first();

        if($credit){
            $credit->update([
                $key => $value
            ]);
        }
    }
}



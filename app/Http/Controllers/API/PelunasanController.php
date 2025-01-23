<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
use Exception;
use Illuminate\Database\QueryException;
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
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');
    
            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            // $kwitansi = $this->saveKwitansi($request, $detail_customer, $no_inv);
            
            $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            if (array_sum($discounts) > 0){
                $status = "PENDING";
            }elseif (strtolower($request->METODE_PEMBAYARAN) === 'transfer') {
                $status = "PENDING";
            }else{
                $status = "PAID";
            }
    
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);
            // $installmentCounts = $creditSchedule->pluck('INSTALLMENT_COUNT')->join(',');
        
            // M_Payment::create([
            //     'ID' => $uid,
            //     'ACC_KEY' => 'pelunasan',
            //     'STTS_RCRD' => $status,
            //     'INVOICE' => $no_inv,
            //     'NO_TRX' => $request->uid,
            //     'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN,
            //     'BRANCH' => $getCodeBranch->CODE_NUMBER,
            //     'LOAN_NUM' => $request->LOAN_NUMBER,
            //     'ENTRY_DATE' => Carbon::now(),
            //     'TITLE' => 'pelunasan',
            //     'ORIGINAL_AMOUNT' => $request->TOTAL_BAYAR,
            //     'OS_AMOUNT' => 0,
            //     'AUTH_BY' => $request->user()->id,
            //     'AUTH_DATE' => Carbon::now()
            // ]);
    
            // $payments = [
            //     'BAYAR_POKOK' => 'BAYAR PELUNASAN POKOK',
            //     'BAYAR_BUNGA' => 'BAYAR PELUNASAN BUNGA',
            //     'BAYAR_PINALTI' => 'BAYAR PELUNASAN PINALTY',
            //     'BAYAR_DENDA' => 'BAYAR PELUNASAN DENDA'
            // ];
        
            // foreach ($payments as $key => $description) {
            //     if ($request->$key != 0) {
            //         $data = $this->preparePaymentData($uid, $description, $request->$key);
            //         M_PaymentDetail::create($data);
            //     }
            // }
        
            // $discounts = [
            //     'DISKON_POKOK' => 'DISKON POKOK',
            //     'DISKON_BUNGA' => 'DISKON BUNGA',
            //     'DISKON_PINALTI' => 'DISKON PINALTY',
            //     'DISKON_DENDA' => 'DISKON DENDA'
            // ];
        
            // foreach ($discounts as $key => $description) {
            //     if ($request->$key != 0) {
            //         $data = $this->preparePaymentData($uid, $description, $request->$key);
            //         M_PaymentDetail::create($data);
            //     }
            // }

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
                // Get current values
                $valBeforePrincipal = $res['PAYMENT_VALUE_PRINCIPAL'];
                $valBeforeInterest = $res['PAYMENT_VALUE_INTEREST'];
                $getPrincipal = $res['PRINCIPAL'];
                $getInterest = $res['INTEREST'];

                // Initialize new payment values
                $new_payment_value_principal = $valBeforePrincipal;

                // Apply principal payment logic
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

                    // Prepare update array for principal
                    $updates = [];
                    if ($new_payment_value_principal !== $valBeforePrincipal) {
                        $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;
                    }

                    if ($remaining_discount > 0) {
                        // Calculate remaining principal that can still be discounted
                        $remaining_to_principal_for_discount = $getPrincipal - $new_payment_value_principal;

                        // If the remaining discount can cover the remaining principal
                        if ($remaining_discount >= $remaining_to_principal_for_discount) {
                            $updates['DISCOUNT_PRINCIPAL'] = $remaining_to_principal_for_discount;
                            $new_payment_value_principal += $remaining_to_principal_for_discount; // Apply discount
                            $remaining_discount -= $remaining_to_principal_for_discount; // Reduce the discount
                        } else {
                            // If the discount can't fully cover the remaining principal
                            $updates['DISCOUNT_PRINCIPAL'] = $remaining_discount;
                            $new_payment_value_principal += $remaining_discount; // Apply discount
                            $remaining_discount = 0; // No more discount left
                        }
                    }

                    if ($valBeforeInterest < $getInterest) {
                        $remaining_to_interest = $getInterest - $valBeforeInterest;
                        $interestUpdates = $this->hitungBunga($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res);
                        $bayarBunga = $interestUpdates['bayarBunga']; // Update the remaining bayarBunga
                        $remaining_discount_bunga = $interestUpdates['remaining_discount_bunga']; // Update the remaining discount for bunga
                    }

                    if (!empty($updates)) {
                        $res->update($updates);
                    }
                }

                $res->update(['PAID_FLAG' =>'PAID']);
                if ($remaining_discount <= 0) {
                    break;
                }                
            }

            // $arrears =  M_Arrears::where(['LOAN_NUMBER' => $loan_number,'STATUS_REC' => 'A'])->get();
                 
            // $bayarDenda = $request->BAYAR_DENDA;
            // $bayarDiscountDenda = $request->DISKON_DENDA;

            // foreach ($arrears as $list) {
            //         $current_penalty = $check_arrears->PAID_PENALTY;
        
            //         $new_penalty = $current_penalty + $bayar_denda;
        
            //         $valBeforePrincipal = $check_arrears->PAID_PCPL;
            //         $valBeforeInterest = $check_arrears->PAID_INT;
            //         $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            //         $getInterest = $check_arrears->PAST_DUE_INTRST;
            //         $getPenalty = $check_arrears->PAST_DUE_PENALTY;
        
            //         $new_payment_value_principal = $valBeforePrincipal;
            //         $new_payment_value_interest = $valBeforeInterest;
        
            //         if ($valBeforePrincipal < $getPrincipal) {
            //             $remaining_to_principal = $getPrincipal - $valBeforePrincipal;
        
            //             if ($byr_angsuran >= $remaining_to_principal) {
            //                 $new_payment_value_principal = $getPrincipal;
            //                 $remaining_payment = $byr_angsuran - $remaining_to_principal;
            //             } else {
            //                 $new_payment_value_principal += $byr_angsuran;
            //                 $remaining_payment = 0;
            //             }
            //         } else {
            //             $remaining_payment = $byr_angsuran;
            //         }
        
            //         if ($new_payment_value_principal == $getPrincipal) {
            //             if ($valBeforeInterest < $getInterest) {
            //                 $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
            //             }
            //         }
        
            //         $updates = [];
            //         if ($new_payment_value_principal !== $valBeforePrincipal) {
            //             $updates['PAID_PCPL'] = $new_payment_value_principal;
            //         }
        
            //         if ($new_payment_value_interest !== $valBeforeInterest) {
            //             $updates['PAID_INT'] = $new_payment_value_interest;
            //         }
        
            //         $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            //         M_PaymentDetail::create($data);
            //         $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);
        
            //         $updates['PAID_PENALTY'] = $new_penalty;
            //         $updates['END_DATE'] = now();   
            //         $updates['UPDATED_AT'] = now();           
                    
            //         if (!empty($updates)) {
            //             $check_arrears->update($updates);
            //         }
        
            //         $total1= floatval($new_payment_value_principal) + floatval($new_payment_value_interest) + floatval($new_penalty);
            //         $total2= floatval($getPrincipal) + floatval($getInterest) + floatval($getPenalty);
        
            //         if ($total1 == $total2) {
            //             $check_arrears->update(['STATUS_REC' => 'S']);
            //         }
            // }
            

    
            // $response = $this->prepareResponse($no_inv, $detail_customer, $request);
            DB::commit();
    
            return response()->json('ok', 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
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
    
    private function updateCredit($credit, $request)
    {
        $credit->update([
            'PAID_PRINCIPAL' => $request->BAYAR_POKOK,
            'PAID_INTEREST' => $request->BAYAR_BUNGA,
            'PAID_PINALTY' => $request->BAYAR_PINALTI,
            'STATUS' => $credit->PCPL_ORI == $request->BAYAR_POKOK ? 'D' : 'A'
        ]);
    }
    
    private function saveKwitansi($request, $customer, $no_inv)
    {
        $data = [
            "PAYMENT_TYPE" => 'pelunasan',
            "STTS_PAYMENT" => $request->METODE_PEMBAYARAN == 'cash' ? "PAID" : "PENDING",
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->LOAN_NUMBER,
            "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
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

    function preparePaymentData($payment_id,$acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }
    
    private function prepareResponse($no_inv, $customer, $request)
    {
        return [
            "no_transaksi" => $no_inv,
            'cust_code' => $customer->CUST_CODE,
            'nama' => $customer->NAME,
            'alamat' => $customer->ADDRESS,
            'rt' => $customer->RT,
            'rw' => $customer->RW,
            'provinsi' => $customer->PROVINCE,
            'kota' => $customer->CITY,
            'kelurahan' => $customer->KELURAHAN,
            'kecamatan' => $customer->KECAMATAN,
            "tgl_transaksi" => Carbon::now()->format('d-m-Y'),
            "payment_method" => $request->METODE_PEMBAYARAN,
            "nama_bank" => $request->NAMA_BANK,
            "no_rekening" => $request->NO_REKENING,
            "bukti_transfer" => '',
            "pembayaran" => 'PELUNASAN',
            "pembulatan" => $request->PEMBULATAN,
            "kembalian" => $request->KEMBALIAN,
            "jumlah_uang" => $request->UANG_PELANGGAN,
            "terbilang" => bilangan($request->TOTAL_BAYAR) ?? null,
            "created_by" => $request->user()->fullname,
            "created_at" => Carbon::parse(Carbon::now())->format('d-m-Y')
        ];
    }
    
}



<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Kwitansi;
use App\Http\Resources\R_PaymentCancelLog;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentApproval;
use App\Models\M_PaymentAttachment;
use App\Models\M_PaymentCancelLog;
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{

    public function index(Request $request){
        try {
            $data = M_Kwitansi::where('PAYMENT_TYPE', 'angsuran')->get();

            $dto = R_Kwitansi::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $created_now = Carbon::now();
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            // Fetch branch information
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            // Initialize variables
            $customer_detail = [];
            $pembayaran = [];

            $request->merge(['approval' => 'approve']);

            // $addAnsguran = [];

            if (isset($request->struktur) && is_array($request->struktur)) {

                // $addAnsguran = array_map(fn($res) => $res['angsuran_ke'], $request->struktur);
                // $uniqueInstallments = array_unique($addAnsguran);
                // sort($uniqueInstallments);
                
                // // Get the minimum value of the installments to compare the sequence
                // $minInstallment = min($uniqueInstallments);
                // $isSequential = $uniqueInstallments === range($minInstallment, $minInstallment + count($uniqueInstallments) - 1);
                
                // if (!$isSequential) {
                //     throw new Exception("Installments tidak berurutan: " . implode(', ', $addAnsguran));
                // }
                
                // Process each installment
                foreach ($request->struktur as $res) {
                    $check_method_payment = strtolower($request->payment_method) === 'cash';

                    // if ($res['angsuran_ke'] && $res['angsuran_ke'] != 1) {
                    //     $previousAngsuranKe = $res['angsuran_ke'] - 1;

                    //     $checkBeforeInstallment = M_KwitansiStructurDetail::where('loan_number', $res['loan_number'])
                    //                                       ->where('angsuran_ke', $previousAngsuranKe)
                    //                                       ->first();

                    //     if (!$checkBeforeInstallment) {
                    //         throw new Exception("Installment {$previousAngsuranKe} not found. Cannot process installment {$res['angsuran_ke']}.");
                    //     }
                    // }
                    
                    $credit = M_Credit::where('LOAN_NUMBER', $res['loan_number'])->first();

                    if (!$credit) {
                        throw new Exception('Loan Number No Exist in Record');
                    }

                    $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->first();

                    if (!$detail_customer) {
                        throw new Exception('Customer No Exist in Record');
                    }

                    $this->setCustomerDetail($detail_customer);

                    M_KwitansiStructurDetail::create([
                        "no_invoice" => $no_inv,
                        "key" => $res['key'],
                        'angsuran_ke' => $res['angsuran_ke'],
                        'loan_number' => $res['loan_number'],
                        'tgl_angsuran' => $res['tgl_angsuran'],
                        'principal' => $res['principal'],
                        'interest' => $res['interest'],
                        'installment' => $res['installment'],
                        'principal_remains' => $res['principal_remains'],
                        'payment' => $res['payment'],
                        'bayar_angsuran' => $res['bayar_angsuran'],
                        "bayar_denda" => $res['bayar_denda'],
                        "total_bayar" => $res['total_bayar'],
                        "flag" => '',
                        "denda" => $res['denda']
                    ]);
            
                    if ($check_method_payment) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv, 'PAID');
                    } else {
                        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
                        M_CreditSchedule::where([
                            'LOAN_NUMBER' => $res['loan_number'],
                            'PAYMENT_DATE' => $tgl_angsuran
                        ])->update(['PAID_FLAG' => 'PENDING']);
                    }
                }
            }
            
            $this->saveKwitansi($request, $detail_customer, $no_inv);

            if($request->penangguhan_denda != 'yes'){
                $this->updateTunggakkanBunga($request);
            }

            $build = $this->buildResponse($request, $detail_customer, $pembayaran, $no_inv, $created_now);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json($build, 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv,$status_paid)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        $stts_apprve = $request->approval === 'approve';
      
        if($stts_apprve){
            $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res);
            $this->updateArrears($loan_number, $tgl_angsuran, $res['bayar_denda'], $res);
        }
    
        $this->createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch,$status_paid);
    }

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        if ($credit_schedule) {
            $byr_angsuran = $res['bayar_angsuran'];
            $payment_value = $byr_angsuran + $credit_schedule->PAYMENT_VALUE;

            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            // Determine the new principal payment value
            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            // Check if principal has already reached the maximum
            if ($valBeforePrincipal < $getPrincipal) {
                // Calculate how much can still be added to the principal
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    // If the payment covers the remaining principal
                    $new_payment_value_principal = $getPrincipal; // Set to maximum
                    $remaining_payment = $byr_angsuran - $remaining_to_principal; // Remaining payment goes to interest
                } else {
                    // If the payment is less than the remaining principal
                    $new_payment_value_principal += $byr_angsuran; // Add to principal
                    $remaining_payment = 0; // No remaining payment to apply to interest
                }
            } else {
                // If principal is already at maximum, we add all to interest
                $remaining_payment = $byr_angsuran;
            }

            // Update interest with remaining payment only if principal is fully paid
            if ($new_payment_value_principal == $getPrincipal) {
                // Only update interest if it has not reached the max
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            // Prepare updates only if values have changed
            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;
            }

            // Only update interest if it has changed
            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;
            }

            // Calculate insufficient payment
            $total_paid = $new_payment_value_principal + $new_payment_value_interest;

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                ? ($total_paid - $credit_schedule->INSTALLMENT)
                : 0;

            // Prepare the update data
            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;
            $updates['PAYMENT_VALUE'] = $payment_value;

            // Update the schedule if there are any changes
            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            $credit_schedule->update(['PAID_FLAG' => $credit_schedule->PAYMENT_VALUE >= $credit_schedule->INSTALLMENT ? 'PAID' : '']);

        }
    }

    private function updateArrears($loan_number, $tgl_angsuran, $bayar_denda,$res)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        if ($check_arrears) {
            // Get the current value of PAID_PENALTY
            $current_penalty = $check_arrears->PAID_PENALTY;

            // Sum the current penalty with the new value
            $new_penalty = $current_penalty + $bayar_denda;

            $byr_angsuran = $res['bayar_angsuran'];

            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            // Determine the new principal payment value
            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            // Check if principal has already reached the maximum
            if ($valBeforePrincipal < $getPrincipal) {
                // Calculate how much can still be added to the principal
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    // If the payment covers the remaining principal
                    $new_payment_value_principal = $getPrincipal; // Set to maximum
                    $remaining_payment = $byr_angsuran - $remaining_to_principal; // Remaining payment goes to interest
                } else {
                    // If the payment is less than the remaining principal
                    $new_payment_value_principal += $byr_angsuran; // Add to principal
                    $remaining_payment = 0; // No remaining payment to apply to interest
                }
            } else {
                // If principal is already at maximum, we add all to interest
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $updates['PAID_PENALTY'] = $new_penalty;
            
            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $total1= floatval($new_payment_value_principal) + floatval($new_payment_value_interest) + floatval($new_penalty);
            $total2= floatval($getPrincipal) + floatval($getInterest) + floatval($getPenalty);

            if ($total1 == $total2) {
                $check_arrears->update(['STATUS_REC' => 'S']);
            }
        }

    }

    private function updateTunggakkanBunga($request)
    {
        $check_arrears = M_Arrears::where('LOAN_NUMBER', $request->no_facility)
                                    ->select('ID', 'PAST_DUE_PENALTY', 'PAID_PENALTY')
                                    ->get();

        $getTunggakan = $request->tunggakan_denda - $request->diskon_tunggakan; 

        $updates = [];
       
        if ($check_arrears->isNotEmpty()) {
            foreach ($check_arrears as $value) {
                $pasDuePenalty = $value->PAST_DUE_PENALTY; 
                $paidPenalty = $value->PAID_PENALTY;
                $arrearsId = $value->ID;
    
                if ($pasDuePenalty != $paidPenalty) {
                    $remainingPenalty = $pasDuePenalty - $paidPenalty;
    
                    if ($getTunggakan >= $remainingPenalty) {
                        $paidPenalty += $remainingPenalty;
                        $getTunggakan -= $remainingPenalty;
                    } else {
                        $paidPenalty += $getTunggakan;
                        $getTunggakan = 0;
                    }
    
                    // Store the update in the array
                    $updates[$arrearsId] = $paidPenalty;
                }
            }    
            // Perform batch update
            foreach ($updates as $id => $paidPenalty) {
               M_Arrears::where('ID', $id)
                         ->update(['PAID_PENALTY' => $paidPenalty]);
            }
        }
    }

    private function saveKwitansi($request, $customer_detail, $no_inv)
    {

        $save_kwitansi = [
            "PAYMENT_TYPE" => 'angsuran',
            "PAYMENT_ID" => $request->uid,
            "STTS_PAYMENT" => $request->payment_method == 'cash' ? "PAID" : "PENDING",
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->no_facility ?? null,
            "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
            'CUST_CODE' => $customer_detail['CUST_CODE'],
            'BRANCH_CODE' => $request->user()->branch_id,
            'NAMA' => $customer_detail['NAME'],
            'ALAMAT' => $customer_detail['ADDRESS'],
            'RT' => $customer_detail['RT'],
            'RW' => $customer_detail['RW'],
            'PROVINSI' => $customer_detail['PROVINCE'],
            'KOTA' => $customer_detail['CITY'],
            'KELURAHAN' => $customer_detail['KELURAHAN'],
            'KECAMATAN' => $customer_detail['KECAMATAN'],
            "METODE_PEMBAYARAN" => $request->payment_method ?? null,
            "TOTAL_BAYAR" => $request->total_bayar ?? null,
            "DISKON" => $request->diskon_tunggakan ?? null,
            "PEMBULATAN" => $request->pembulatan ?? null,
            "KEMBALIAN" => $request->kembalian ?? null,
            "JUMLAH_UANG" => $request->jumlah_uang ?? null,
            "NAMA_BANK" => $request->nama_bank ?? null,
            "NO_REKENING" => $request->no_rekening ?? null,
            "CREATED_BY" => $request->user()->fullname
        ];

        M_Kwitansi::create($save_kwitansi);
    }

    private function buildResponse($request, $customer_detail, $pembayaran, $no_inv, $created_now)
    {
        return [
            "no_transaksi" => $no_inv,
            'cust_code' => $customer_detail['CUST_CODE'],
            'nama' => $customer_detail['NAME'],
            'alamat' => $customer_detail['ADDRESS'],
            'rt' => $customer_detail['RT'],
            'rw' => $customer_detail['RW'],
            'provinsi' => $customer_detail['PROVINCE'],
            'kota' => $customer_detail['CITY'],
            'kelurahan' => $customer_detail['KELURAHAN'],
            'kecamatan' => $customer_detail['KECAMATAN'],
            "tgl_transaksi" => Carbon::now()->format('d-m-Y'),
            "payment_method" => $request->payment_method,
            "nama_bank" => $request->nama_bank,
            "no_rekening" => $request->no_rekening,
            "bukti_transfer" => '',
            "pembayaran" => $pembayaran,
            "pembulatan" => $request->pembulatan,
            "kembalian" => $request->kembalian,
            "jumlah_uang" => $request->jumlah_uang,
            "terbilang" => bilangan($request->total_bayar) ?? null,
            "created_by" => $request->user()->fullname,
            "created_at" => Carbon::parse($created_now)->format('d-m-Y')
        ];
    }

    function setCustomerDetail($customer)
    {
        return [
            'cust_code' => $customer->CUST_CODE,
            'nama' => $customer->NAME,
            'alamat' => $customer->ADDRESS,
            'rt' => $customer->RT,
            'rw' => $customer->RW,
            'provinsi' => $customer->PROVINCE,
            'kota' => $customer->CITY,
            'kelurahan' => $customer->KELURAHAN,
            'kecamatan' => $customer->KECAMATAN,
        ];
    }

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $status_paid)
    {
        $uid = Uuid::uuid7()->toString();

        $check = M_Payment::where('LOAN_NUM', $loan_number)
            ->where('STTS_RCRD', 'PAID')
            ->latest('BANK_NAME')
            ->first();

        $getPayments = M_Payment::where('LOAN_NUM', $loan_number)
            ->where('START_DATE', $tgl_angsuran)
            ->leftJoin('payment_detail', 'payment_detail.PAYMENT_ID', '=', 'payment.ID')
            ->select('payment_detail.ACC_KEYS', 'payment_detail.ORIGINAL_AMOUNT')
            ->get();
        
        $kwitansi = M_Kwitansi::where('LOAN_NUMBER',$loan_number)->first();
        
        $payments = [];

        $totalAmount = 0;
        foreach ($getPayments as $payment) {
            $payments[$payment->ACC_KEYS] = $payment->ORIGINAL_AMOUNT;
            
            if ($payment->ACC_KEYS === 'ANGSURAN_POKOK') {
                $totalAmount += $payment->ORIGINAL_AMOUNT;
            }
        }

        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        if ($credit_schedule) {
            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $getPayPrincipal = isset($payments['ANGSURAN_POKOK']) ? intval($totalAmount) : 0;
            $getPayInterest = isset($payments['ANGSURAN_BUNGA']) ? intval($payments['ANGSURAN_BUNGA']) : 0;

            if ($getPayPrincipal !== $getPrincipal) {
                $setPrincipal = $valBeforePrincipal - $getPayPrincipal;
                if(is_null($check)) {
                    $pokok = floatval($res['bayar_angsuran']) > floatval($res['principal']) 
                             ? floatval($res['principal_remains']) 
                             : ((floatval($res['principal_remains']) + floatval($res['principal'])) - $res['bayar_angsuran']);
                    
                    $os_amount = round($pokok, 2);
                } else {
                  
                    $os_amount = round($check->OS_AMOUNT - $setPrincipal, 2);
                }
            }
        }

        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $payment_record = [
            'ID' => $uid,
            'ACC_KEY' => isset($request->pembayaran)?$request->pembayaran:$kwitansi->METODE_PEMBAYARAN??null,
            'STTS_RCRD' => $status_paid,
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid,
            'PAYMENT_METHOD' => $request->payment_method,
            'BRANCH' => $branch->CODE_NUMBER,
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda??'',
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => ($res['bayar_angsuran']+$res['bayar_denda']),
            'OS_AMOUNT' => $os_amount??0,
            'START_DATE' => $tgl_angsuran,
            'END_DATE' => now(),
            'USER_ID' => $request->user()->id,
            'ARREARS_ID' => $check_arrears?$check_arrears->ID:'',
            'BANK_NAME' => round(microtime(true) * 1000)
        ];

        M_Payment::create($payment_record);

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($credit_schedule) {
            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $getPayPrincipal = isset($payments['ANGSURAN_POKOK'])? floatval($totalAmount):0;
            $getPayInterest = isset($payments['ANGSURAN_BUNGA']) ? floatval($payments['ANGSURAN_BUNGA']) : 0;

            if ($getPayPrincipal != $getPrincipal) {
                $setPrincipal = bcsub($valBeforePrincipal, $getPayPrincipal, 2);
                $setPrincipal = ceil($setPrincipal * 100) / 100;
                $data_principal = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $setPrincipal);
                M_PaymentDetail::create($data_principal);
            }
            
            if ($getPayInterest !== $getInterest) {
                $setInterest = $valBeforeInterest - $getPayInterest;
                $data_interest = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $setInterest);
                M_PaymentDetail::create($data_interest);
            }

            $bayar_denda = $res['bayar_denda'];

            if ($bayar_denda != 0 || $request->penangguhan_denda == 'yes') {
                $data_denda = $this->preparePaymentData($uid, 'DENDA_PINJAMAN', $bayar_denda);
                $setPenalty = floatval($check_credit->PAID_PINALTY??0) + floatval($bayar_denda??0);
                M_PaymentDetail::create($data_denda);
            }

            if ($check_credit) {
                $paidPrincipal = isset($setPrincipal) ? bcadd($check_credit->PAID_PRINCIPAL ?? '0.00', $setPrincipal, 2) : ($check_credit->PAID_PRINCIPAL ?? '0.00');
                $paidInterest = isset($setInterest) ? bcadd($check_credit->PAID_INTEREST ?? '0.00', $setInterest, 2) : ($check_credit->PAID_INTEREST ?? '0.00');
                $paidPenalty = isset($setPenalty) ? $setPenalty : 0;
            
                $checkCreditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
                                        ->where(function ($query) {
                                            $query->where('PAID_FLAG', '')
                                                ->orWhereNull('PAID_FLAG');
                                        })
                                        ->first();

                $checkArrears = M_Arrears::where([
                    'LOAN_NUMBER' => $loan_number,
                    'STATUS_REC' => 'A'
                ])->first();


                // $status = bccomp($paidPrincipal, $check_credit->PCPL_ORI, 2) >= 0 ? 'D' : 'A';
                $status = (!$checkCreditSchedule && !$checkArrears) ? 'D' : 'A';
            
                // Update database dengan nilai presisi tinggi
                $check_credit->update([
                    'PAID_PRINCIPAL' => $paidPrincipal,
                    'PAID_INTEREST' => $paidInterest,
                    'PAID_PINALTY' => $paidPenalty,
                    'STATUS' => $status,
                ]);
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

    public function destroyImage(Request $req,$id)
    {
        DB::beginTransaction();
        try {
            $check = M_PaymentAttachment::findOrFail($id);

            $check->delete();

            DB::commit();
            ActivityLogger::logActivity($req,"deleted successfully",200);
            return response()->json(['message' => 'deleted successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Document Id Not Found', 404);
            return response()->json(['message' => 'Document Id Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function upload(Request $req)
    {
        DB::beginTransaction();
        try {

            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);
    
                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;
    
                // Store the image
                $image_path = Storage::put("public/Payment/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);
              
                $url = URL::to('/') . '/storage/' .'Payment/'. $fileName;
    
                // Prepare data for database insertion
                $data_array_attachment = [
                    'id' => Uuid::uuid4()->toString(),
                    'payment_id' => $req->uid,
                    'file_attach' => $url ?? ''
                ];

                $check = M_PaymentAttachment::where('payment_id',$req->uid)->first();

                if($check){
                    $check->delete();
                }

                M_PaymentAttachment::create($data_array_attachment);
    
                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }

           
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function approval(Request $request)
    {
        DB::beginTransaction();
        try {

            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            $kwitansi = M_Kwitansi::where('NO_TRANSAKSI',$request->no_invoice)->firstOrFail();

            $request->merge(['payment_method' => 'transfer']);

            if($request->flag == 'yes'){
                $request->merge(['approval' => 'approve']);
                if (isset($request->struktur) && is_array($request->struktur)) {
                    foreach ($request->struktur as $res) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $request->no_invoice,'PAID');
                    }
                }
                $kwitansi->update(['STTS_PAYMENT' => 'PAID']);
            }else{
                $request->merge(['approval' => 'no']);

                if (isset($request->struktur) && is_array($request->struktur)) {
                    foreach ($request->struktur as $res) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $request->no_invoice,'CANCEL');

                        $credit_schedule = M_CreditSchedule::where([
                            'LOAN_NUMBER' => $res['loan_number'],
                            'PAYMENT_DATE' => Carbon::parse($res['tgl_angsuran'])->format('Y-m-d')
                        ])->first();
    
                        $credit_schedule->update(['PAID_FLAG' => null]);
                    }
                }

                $kwitansi->update(['STTS_PAYMENT' => 'CANCEL']);
            }
            
            $data_approval = [
                'PAYMENT_ID' => $request->no_invoice,
                'ONCHARGE_APPRVL' => $request->flag,
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'ONCHARGE_DESCR' => $request->keterangan,
                'APPROVAL_RESULT' => $request->flag == 'yes' ? 'PAID' : 'CANCEL'
            ];

            M_PaymentApproval::create($data_approval);
            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    } 

    public function cancel(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'no_invoice' => 'required|string',
                'flag' => 'in:yes,no',
            ]);

            $no_invoice = $request->no_invoice;
            $flag = $request->flag;

            $check = M_Kwitansi::where([
                'NO_TRANSAKSI' => $no_invoice,
                'STTS_PAYMENT' => 'PAID'
            ])->first();

            if (!$check) {
                throw new Exception("Kwitansi Number Not Exist", 404);
            }

            $checkPaymentLog = M_PaymentCancelLog::where('INVOICE_NUMBER',$no_invoice)->first();

            if(!$checkPaymentLog){
                M_PaymentCancelLog::create([
                    'INVOICE_NUMBER' => $no_invoice??'',
                    'REQUEST_BY' => $request->user()->id??'',
                    'REQUEST_BRANCH' => $request->user()->branch_id??'',
                    'REQUEST_POSITION' => $request->user()->position??'',
                    'REQUEST_DESCR' => $request->descr??'',
                    'REQUEST_DATE' => Carbon::now()
                ]);
            }

            if (strtolower($request->user()->position) == 'ho' && isset($flag) || !empty($flag) ) {
                 $this->processHoApproval($request, $check);
            }

            DB::commit();
            return response()->json(['message' => "Invoice Number {$no_invoice} Cancel Success"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function processHoApproval(Request $request, $check)
    {
        if (strtolower($request->flag) === 'yes') {

            $check->update([
                'STTS_PAYMENT' => 'CANCEL'  
            ]);
            
            $paymentCheck = DB::table('payment as a')
                                ->leftJoin('payment_detail as b', 'b.PAYMENT_ID', '=', 'a.ID')
                                ->select('a.LOAN_NUM','a.START_DATE' ,'a.ORIGINAL_AMOUNT','b.ACC_KEYS', 'b.ORIGINAL_AMOUNT as amount')
                                ->where('a.INVOICE', $request->no_invoice)
                                ->get();

            $loan_number = '';
            $totalPrincipal = 0;
            $totalInterest = 0;
            $totalPenalty = 0;
            $creditSchedule = [];

            if(!empty($paymentCheck)){
                foreach ($paymentCheck as $list) {

                    M_Payment::where('INVOICE', $request->no_invoice)
                                ->update([
                                    'STTS_RCRD' => 'CANCEL'
                                ]);
    
                   $loan_number = $list->LOAN_NUM;
    
                   if (!isset($creditSchedule[$list->START_DATE])) {
                        $creditSchedule[$list->START_DATE] = [
                            'LOAN_NUMBER' => $list->LOAN_NUM,
                            'PAYMENT_DATE' => date('Y-m-d', strtotime($list->START_DATE)),
                            'AMOUNT' => floatval($list->ORIGINAL_AMOUNT),
                            'PRINCIPAL' => 0, 
                            'INTEREST' => 0,  
                            'PENALTY' => 0
                        ];
                    }
    
                    switch ($list->ACC_KEYS) {
                        case 'ANGSURAN_POKOK':
                            $creditSchedule[$list->START_DATE]['PRINCIPAL'] = $list->amount??0;
                            break;
                        case 'ANGSURAN_BUNGA':
                            $creditSchedule[$list->START_DATE]['INTEREST'] = $list->amount??0;
                            break;
                        case 'DENDA_PINJAMAN':
                            $creditSchedule[$list->START_DATE]['PENALTY'] = $list->amount??0;
                            break;
                    }
                }
            }

            foreach ($creditSchedule as $schedule) {
                $totalPrincipal += $schedule['PRINCIPAL'];
                $totalInterest += $schedule['INTEREST'];
                $totalPenalty += $schedule['PENALTY'];
            }

            $setPrincipal = round($totalPrincipal, 2);

            $creditCheck = M_Credit::where('LOAN_NUMBER', $loan_number)
                                    ->whereIn('STATUS', ['A', 'D'])
                                    ->first();
    
            if($creditCheck){
                $creditCheck->update([
                    'STATUS' => 'A',
                    'PAID_PRINCIPAL' => floatval($creditCheck->PAID_PRINCIPAL)-floatval($setPrincipal??0),
                    'PAID_INTEREST' => floatval($creditCheck->PAID_INTEREST??0)-floatval($totalInterest??0),
                    'PAID_PENALTY' => floatval($creditCheck->PAID_PENALTY??0)-floatval($totalPenalty??0),
                    'MOD_USER' => $request->user()->id,
                    'MOD_DATE' => Carbon::now(),
                ]);
            }

            if(!empty($creditSchedule)){
                foreach ($creditSchedule as $resList) {
                    $creditScheduleCheck = M_CreditSchedule::where([
                        'LOAN_NUMBER' => $loan_number,
                        'PAYMENT_DATE' => $resList['PAYMENT_DATE']
                    ])->first();

                    if($creditScheduleCheck){
                        $creditScheduleCheck->update([
                            'PAID_FLAG' => '',
                            'PAYMENT_VALUE_PRINCIPAL' =>  floatval($creditScheduleCheck->PAYMENT_VALUE_PRINCIPAL??0) -  floatval($resList['PRINCIPAL']??0),
                            'PAYMENT_VALUE_INTEREST' =>  floatval($creditScheduleCheck->PAYMENT_VALUE_INTEREST??0) -  floatval($resList['INTEREST']??0),
                            'PAYMENT_VALUE' => floatval($creditScheduleCheck->PAYMENT_VALUE ?? 0) - floatval($resList['AMOUNT'] ?? 0)
                        ]);
                    }

                    $arrearsCheck = M_Arrears::where([
                        'LOAN_NUMBER' => $loan_number,
                        'START_DATE' => $resList['PAYMENT_DATE']
                    ])->first();

                    if($arrearsCheck){
                        $arrearsCheck->update([
                            'STATUS_REC' => 'A',
                            'PAID_PCPL' =>  floatval($arrearsCheck->PAID_PCPL??0) -  floatval($resList['PRINCIPAL']??0),
                            'PAID_INT' =>  floatval($arrearsCheck->PAID_INT??0) -  floatval($resList['INTEREST']??0),
                            'PAID_PENALTY' => floatval($arrearsCheck->PAID_PENALTY ?? 0) - floatval($resList['PENALTY'] ?? 0)
                        ]);
                    }
                }
            }
        }

        $checkCreditCancel = M_PaymentCancelLog::where('INVOICE_NUMBER', $request->no_invoice)->first();
    
        if($checkCreditCancel){
            $checkCreditCancel->update([
                'ONCHARGE_DESCR' => $request->descr_ho ?? '',
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'ONCHARGE_FLAG' => $request->flag??'',
            ]);
        }
    }

    public function cancelList(Request $request)
    {
        try {
            $data = M_PaymentCancelLog::where(function($query) {
                        $query->whereNull('ONCHARGE_PERSON')
                            ->orWhere('ONCHARGE_PERSON', '');
                    })
                    ->where(function($query) {
                        $query->whereNull('ONCHARGE_TIME')
                            ->orWhere('ONCHARGE_TIME', '');
                    })
                    ->get();

            $dto = R_PaymentCancelLog::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

}

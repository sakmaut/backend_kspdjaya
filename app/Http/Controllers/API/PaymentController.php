<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Kwitansi;
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
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $no_inv = generateCode($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            // Fetch branch information
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            // Initialize variables
            $customer_detail = [];
            $pembayaran = [];

            $request->merge(['approval' => 'approve']);

            // Process payment structures
            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $res) {
                    $check_method_payment = strtolower($request->payment_method) === 'cash';

                    // Fetch credit and customer details once
                    $credit = M_Credit::where('LOAN_NUMBER', $res['loan_number'])->firstOrFail();
                    $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();
                    $customer_detail =$this->setCustomerDetail($detail_customer);

                    $pembayaran[] = [
                        'installment' => $res['angsuran_ke'],
                        'title' => 'Angsuran Ke-' . $res['angsuran_ke']
                    ];
            
                    // Set customer details
                    $save_kwitansi_detail = [
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
                    ];
            
                    M_KwitansiStructurDetail::create($save_kwitansi_detail);

                    if ($check_method_payment) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv,'PAID');
                    } else {
                        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
                        M_CreditSchedule::where([
                            'LOAN_NUMBER' => $res['loan_number'],
                            'PAYMENT_DATE' => $tgl_angsuran
                        ])->update(['PAID_FLAG' => 'PENDING']);
                    }
                }
            }

            // Save main kwitansi record
            $this->saveKwitansi($request, $customer_detail, $no_inv);

            // Build response
            $build = $this->buildResponse($request, $customer_detail, $pembayaran, $no_inv, $created_now);

            DB::commit();
            // ActivityLogger::logActivity($request, "Success", 200);
            return response()->json($build, 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage()], 409);
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
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            // Only update interest if it has changed
            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $updates['PAID_PENALTY'] = $new_penalty;
            
            // Update the schedule if there are any changes
            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $total1= floatval($new_payment_value_principal) + floatval($new_payment_value_interest);
            $total2= floatval($getPrincipal) + floatval($getInterest);

            if ($total1 == $total2) {
                $check_arrears->update(['STATUS_REC' => 'D']);
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
            'CUST_CODE' => $customer_detail['cust_code'],
            'BRANCH_CODE' => $request->user()->branch_id,
            'NAMA' => $customer_detail['nama'],
            'ALAMAT' => $customer_detail['alamat'],
            'RT' => $customer_detail['rt'],
            'RW' => $customer_detail['rw'],
            'PROVINSI' => $customer_detail['provinsi'],
            'KOTA' => $customer_detail['kota'],
            'KELURAHAN' => $customer_detail['kelurahan'],
            'KECAMATAN' => $customer_detail['kecamatan'],
            "METODE_PEMBAYARAN" => $request->payment_method ?? null,
            "TOTAL_BAYAR" => $request->total_bayar ?? null,
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
            'cust_code' => $customer_detail['cust_code'],
            'nama' => $customer_detail['nama'],
            'alamat' => $customer_detail['alamat'],
            'rt' => $customer_detail['rt'],
            'rw' => $customer_detail['rw'],
            'provinsi' => $customer_detail['provinsi'],
            'kota' => $customer_detail['kota'],
            'kelurahan' => $customer_detail['kelurahan'],
            'kecamatan' => $customer_detail['kecamatan'],
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

        $totalAmount = 0; // To store the sum of ORIGINAL_AMOUNT
        foreach ($getPayments as $payment) {
            // Build the array
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
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => ($res['bayar_angsuran']+$res['bayar_denda']),
            'OS_AMOUNT' => $os_amount??0,
            'START_DATE' => $tgl_angsuran,
            'END_DATE' => now(),
            'AUTH_BY' => $request->user()->id,
            'AUTH_DATE' => now(),
            'BANK_NAME' => round(microtime(true) * 1000)
        ];

        M_Payment::create($payment_record);

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($credit_schedule) {
            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $getPayPrincipal = isset($payments['ANGSURAN_POKOK'])? intval($totalAmount):0;
            $getPayInterest = isset($payments['ANGSURAN_BUNGA']) ? intval($payments['ANGSURAN_BUNGA']) : 0;

            if($getPayPrincipal != $getPrincipal && $valBeforePrincipal != 0){
                $setPrincipal = $valBeforePrincipal - $getPayPrincipal;
                $data_principal = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $setPrincipal); // Set to PRINCIPAL value
                M_PaymentDetail::create($data_principal);
            }

            // Only update interest if it has changed
            if ($getPayInterest !== $getInterest && $valBeforeInterest != null) {
                $setInterest = $valBeforeInterest - $getPayInterest;
                $data_interest = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $setInterest);
                M_PaymentDetail::create($data_interest);
            }

            $bayar_denda = $res['bayar_denda'];

            if ($bayar_denda !== 0 && $request->payment_method == 'cash') {
                $data_denda = $this->preparePaymentData($uid, 'DENDA_PINJAMAN', $bayar_denda);
                M_PaymentDetail::create($data_denda);
            }

            if ($check_credit) {
                $paidPrincipal = isset($setPrincipal) ? $check_credit->PAID_PRINCIPAL + $setPrincipal : $check_credit->PAID_PRINCIPAL;
                $paidInterest = isset($setInterest) ? $check_credit->PAID_INTEREST + $setInterest : $check_credit->PAID_INTEREST;
                $paidPenalty = $bayar_denda !== 0 ? $check_credit->PAID_PINALTY + $bayar_denda : $check_credit->PAID_PINALTY;

                $check_credit->update([
                    'PAID_PRINCIPAL' => $paidPrincipal,
                    'PAID_INTEREST' => $paidInterest,
                    'PAID_PINALTY' => $paidPenalty
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

    public function upload(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'image|mimes:jpg,png,jpeg,gif,svg',
                'payment_id' =>'string'
            ]);

            if ($req->hasFile('image')) {
                $image_path = $req->file('image')->store('public/Payment');
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . $image_path;

                $data_array_attachment = [
                    'id' => Uuid::uuid4()->toString(),
                    'payment_id' => $req->uid,
                    'file_attach' => $url ?? ''
                ];

                M_PaymentAttachment::create($data_array_attachment);

                DB::commit();
                return response()->json($url, 200);
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

            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    } 
}

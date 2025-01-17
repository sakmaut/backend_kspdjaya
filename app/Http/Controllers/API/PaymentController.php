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

            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);
            $pembayaran = [];

            $detail_customer = DB::table('credit as c')
                                ->join('customer as c2', 'c2.CUST_CODE', '=', 'c.CUST_CODE')
                                ->where('c.LOAN_NUMBER', $request->no_facility)
                                ->select([
                                    'c2.CUST_CODE',
                                    'c2.NAME',
                                    'c2.ADDRESS',
                                    'c2.RT',
                                    'c2.RW',
                                    'c2.PROVINCE',
                                    'c2.CITY',
                                    'c2.KELURAHAN',
                                    'c2.KECAMATAN'
                                ])
                                ->first();

            if (!$detail_customer) {
                throw new Exception('Customer No Exist in Record');
            }

            $this->setCustomerDetail($detail_customer);

            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $res) {

                    $check_method_payment = strtolower($request->payment_method) === 'cash';

                    M_KwitansiStructurDetail::create([
                        "no_invoice" => $no_inv,
                        "key" => $res['key'] ?? '',
                        'angsuran_ke' => $res['angsuran_ke'] ?? '',
                        'loan_number' => $res['loan_number'] ?? '',
                        'tgl_angsuran' => $res['tgl_angsuran'] ?? '',
                        'principal' => $res['principal'] ?? '',
                        'interest' => $res['interest'] ?? '',
                        'installment' => $res['installment'] ?? '',
                        'principal_remains' => $res['principal_remains'] ?? '',
                        'payment' => $res['payment'] ?? '',
                        'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
                        "bayar_denda" => $res['bayar_denda'] ?? '',
                        "total_bayar" => $res['total_bayar'] ?? '',
                        "flag" => $res['flag']??'',
                        "denda" => $res['denda'] ?? ''
                    ]);

                    if ($check_method_payment) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv);
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

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        $uid = Uuid::uuid7()->toString();

        $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res,$uid);
        $this->updateArrears($loan_number, $tgl_angsuran, $res,$uid);
        $this->createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch,$uid);
    }

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res,$uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = $res['bayar_angsuran'];

        if ($credit_schedule && $byr_angsuran != 0) {
            
            $payment_value = $byr_angsuran + $credit_schedule->PAYMENT_VALUE;

            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran; 
                    $remaining_payment = 0; 
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;

                $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;

                $valInterest = $new_payment_value_interest - $valBeforeInterest;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number,['ANGSURAN_BUNGA' => $valInterest]);
            }

            $total_paid = $new_payment_value_principal + $new_payment_value_interest;

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                ? ($total_paid - $credit_schedule->INSTALLMENT)
                : 0;

            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;
            $updates['PAYMENT_VALUE'] = $payment_value;

            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            $credit_schedule->update(['PAID_FLAG' => $credit_schedule->PAYMENT_VALUE >= $credit_schedule->INSTALLMENT ? 'PAID' : '']);

        }
    }

    private function updateArrears($loan_number, $tgl_angsuran,$res,$uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        if ($check_arrears && $bayar_denda != 0) {
            $current_penalty = $check_arrears->PAID_PENALTY;

            $new_penalty = $current_penalty + $bayar_denda;

            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
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

            $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($data);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $updates['PAID_PENALTY'] = $new_penalty;
            $updates['END_DATE'] = now();            
            
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

                    $updates[$arrearsId] = $paidPenalty;
                }
            }    
          
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
            'CUST_CODE' => $customer_detail->CUST_CODE,
            'BRANCH_CODE' => $request->user()->branch_id,
            'NAMA' => $customer_detail->NAME,
            'ALAMAT' => $customer_detail->ADDRESS,
            'RT' => $customer_detail->RT,
            'RW' => $customer_detail->RW,
            'PROVINSI' => $customer_detail->PROVINCE,
            'KOTA' => $customer_detail->CITY,
            'KELURAHAN' => $customer_detail->KELURAHAN,
            'KECAMATAN' => $customer_detail->KECAMATAN,
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
            'cust_code' => $customer_detail->CUST_CODE,
            'nama' => $customer_detail->NAME,
            'alamat' => $customer_detail->ADDRESS,
            'rt' => $customer_detail->RT,
            'rw' => $customer_detail->RW,
            'provinsi' => $customer_detail->PROVINCE,
            'kota' => $customer_detail->CITY,
            'kelurahan' => $customer_detail->KELURAHAN,
            'kecamatan' => $customer_detail->KECAMATAN,
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

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch,$uid)
    {
        if($res['bayar_angsuran'] != 0 && $res['flag'] != 'PAID' || $res['bayar_denda'] != 0){
            M_Payment::create([
                'ID' => $uid,
                'ACC_KEY' => $request->pembayaran ?? '',
                'STTS_RCRD' => 'PAID',
                'INVOICE' => $no_inv,
                'NO_TRX' => $request->uid,
                'PAYMENT_METHOD' => $request->payment_method,
                'BRANCH' => $branch->CODE_NUMBER,
                'LOAN_NUM' => $loan_number,
                'VALUE_DATE' => null,
                'ENTRY_DATE' => now(),
                'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
                'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
                'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
                'OS_AMOUNT' => $os_amount ?? 0,
                'START_DATE' => $tgl_angsuran,
                'END_DATE' => now(),
                'USER_ID' => $request->user()->id,
                'ARREARS_ID' => $res['id_arrear'] ?? '',
                'BANK_NAME' => round(microtime(true) * 1000)
            ]);
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

    public function addCreditPaid($loan_number,array $data){
          $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();
       
          if ($check_credit) {
                $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK']:0;
                $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] :0;
                $paidPenalty = isset($data['BAYAR_DENDA'])? $data['BAYAR_DENDA'] : 0;

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

                $status = (!$checkCreditSchedule && !$checkArrears) ? 'D' : 'A';

                $check_credit->update([
                    'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
                    'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
                    'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty),
                    'STATUS' => $status,
                ]);
            }
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

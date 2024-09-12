<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{

    public function index(Request $request){
        try {
            $data = M_Kwitansi::all();

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
            $getCodeBranch = M_Branch::find($request->user()->branch_id);
            $created_now = Carbon::now();
            $no_inv = generateCode($request, 'payment', 'INVOICE','INV');

            $customer_detail = [];
            $pembayaran = [];
            
            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $res) {

                    $loan_number = $res['loan_number'];
                    $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
            
                    // // Fetch credit and customer details once
                    $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();
                    $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->first();

                    $check_method_payment = strtolower($request->payment_method) == 'cash';

                    if($check_method_payment){
                        $credit_schedule = M_CreditSchedule::where([
                            'LOAN_NUMBER' => $loan_number,
                            'PAYMENT_DATE' => $tgl_angsuran
                        ])->first();

                        if ($credit_schedule) {
                            $credit_schedule->update([
                                'PAYMENT_VALUE' =>  $res['bayar_angsuran'],
                                'PAID_FLAG' => $res['bayar_angsuran'] == $credit_schedule->INSTALLMENT ? 'PAID' : ''
                            ]);
                        }
                
                        // // Update arrears
                        $check_arrears = M_Arrears::where([
                            'LOAN_NUMBER' => $loan_number,
                            'START_DATE' => $tgl_angsuran
                        ])->first();
                
                        if ($check_arrears) {
                            $check_arrears->update([
                                'PAID_PENALTY' => $res['bayar_denda']
                            ]);
                        }
                    }

                    // // Add installment details to pembayaran array
                    $pembayaran[] = [
                        'installment' => $res['angsuran_ke'],
                        'title' => 'Angsuran Ke-' . $res['angsuran_ke']
                    ];
            
                    // // Set customer details
                    $customer_detail = self::setCustomerDetail($detail_customer);
            
                    // Prepare payment data based on installment
                    self::createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch, $created_now);

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
                        "flag" => $res['flag'],
                        "denda" => $res['denda']
                    ];

                    M_KwitansiStructurDetail::create($save_kwitansi_detail);
                }
            }

            $save_kwitansi = [
                "NO_TRANSAKSI" => $no_inv,
                "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
                'CUST_CODE' => $customer_detail['cust_code'],
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
                "NAMA_BANK" => $request->nama_bank??null,
                "NO_REKENING" => $request->no_rekening ?? null,
                "CREATED_BY" => $request->user()->fullname
            ];

            M_Kwitansi::create($save_kwitansi);
            
            // Build response
            $build = [
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
  
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
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

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $created_now)
    {
        // Payment principal
        $data_principal = self::preparePaymentData($request, $loan_number, $res, $tgl_angsuran, 'POKOK', $res['principal'], $no_inv, $branch, $created_now);
        M_Payment::create($data_principal);

        // Payment interest
        $interest_amount = $res['bayar_angsuran'] >= $res['principal'] ? ($res['bayar_angsuran'] - $res['principal']) : 0;
        $data_interest = self::preparePaymentData($request, $loan_number, $res, $tgl_angsuran, 'BUNGA', $interest_amount, $no_inv, $branch, $created_now);
        M_Payment::create($data_interest);

         // Payment Denda
        if($res['bayar_denda'] !== 0){
            $data_interest = self::preparePaymentData($request, $loan_number, $res, $tgl_angsuran, 'DENDA', $res['bayar_denda'], $no_inv, $branch, $created_now);
            M_Payment::create($data_interest);
        }  
    }

    function preparePaymentData($request, $loan_number, $res, $tgl_angsuran, $acc_key, $amount, $no_inv, $branch, $created_now)
    {
        return [
            'ID' => Uuid::uuid7()->toString(),
            'ACC_KEY' => $acc_key,
            'STTS_RCRD' =>  $request->payment_method == 'cash'?'PAID':'PENDING',
            'INVOICE' => $no_inv,
            'NO_TRX' => generateCode($request, 'payment', 'NO_TRX', 'TRX'),
            'PAYMENT_METHOD' => $request->payment_method,
            'BRANCH' => $branch->CODE_NUMBER,
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => $created_now,
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => $amount,
            'OS_AMOUNT' => 0,
            'START_DATE' => $tgl_angsuran,
            'AUTH_BY' => $request->user()->id,
            'AUTH_DATE' => $created_now,
            'BANK_NAME' => $request->nama_bank ?? null,
            'BANK_ACC_NUMBER' => $request->no_rekening ?? null
        ];
    }

    function generateInvoice($id)
    {
        $lastCode = DB::table('branch')->where('ID',$id)->first();

        if ($lastCode) {
            $lastCodeNumber = (int) substr($lastCode->CODE_NUMBER, 1);
            $newCodeNumber = $lastCodeNumber + 1;
            $newCode = str_pad($newCodeNumber, 3, '0', STR_PAD_LEFT);
        } else {
            $newCode = '001';
        }

        return $newCode;
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
                    'payment_id' => $req->payment_id,
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
            $check = M_KwitansiStructurDetail::where('no_invoice', $request->no_invoice)->get();

            foreach ($check as $res) {

                $loan_number = $res['loan_number'];
                $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                $credit_schedule = M_CreditSchedule::where([
                    'LOAN_NUMBER' => $loan_number,
                    'PAYMENT_DATE' => $tgl_angsuran
                ])->first();

                if ($credit_schedule) {
                    $credit_schedule->update([
                        'PAYMENT_VALUE' =>  $res['bayar_angsuran'],
                        'PAID_FLAG' => $res['bayar_angsuran'] == $credit_schedule->INSTALLMENT ? 'PAID' : ''
                    ]);
                }

                // // Update arrears
                $check_arrears = M_Arrears::where([
                    'LOAN_NUMBER' => $loan_number,
                    'START_DATE' => $tgl_angsuran
                ])->first();

                if ($check_arrears) {
                    $check_arrears->update([
                        'PAID_PENALTY' => $res['bayar_denda']
                    ]);
                }
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

            return response()->json(['message' => 'approval success'], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    } 
}

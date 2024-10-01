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
            $no_inv = generateCode($request, 'payment', 'INVOICE', 'INV');

            // Fetch branch information
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            // Initialize variables
            $customer_detail = [];
            $pembayaran = [];

            // Process payment structures
            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $res) {
                    $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv, $created_now, $pembayaran, $customer_detail);
                }
            }
            // Save main kwitansi record
            $this->saveKwitansi($request, $customer_detail, $no_inv, $created_now);

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

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv, $created_now, &$pembayaran, &$customer_detail)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

        // Fetch credit and customer details once
        $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();
        $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

        $check_method_payment = strtolower($request->payment_method) === 'cash';

        if ($check_method_payment) {
            $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res);
            $this->updateArrears($loan_number, $tgl_angsuran, $res['bayar_denda']);
        }else{
            $credit_schedule = M_CreditSchedule::where([
                'LOAN_NUMBER' => $loan_number,
                'PAYMENT_DATE' => $tgl_angsuran
            ])->first();
    
            if ($credit_schedule) {
                $credit_schedule->update([
                    'PAID_FLAG' => 'PENDING'
                ]);
            }
        }

        // Add installment details to pembayaran array
        $pembayaran[] = [
            'installment' => $res['angsuran_ke'],
            'title' => 'Angsuran Ke-' . $res['angsuran_ke']
        ];

        // Set customer details
        $customer_detail = self::setCustomerDetail($detail_customer);

        // Prepare payment data and create records
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

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        if ($credit_schedule) {
            $byr_angsuran = $res['bayar_angsuran'];
            $payment_value = $byr_angsuran + $credit_schedule->PAYMENT_VALUE;
            $remaining_principal = $byr_angsuran - $credit_schedule->PRINCIPAL;

            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            if ($remaining_principal >= 0) {
                // If angsuran can fully cover the principal
                $payment_value_principal = $credit_schedule->PRINCIPAL;

                // Use remaining amount to pay off interest
                $payment_value_interest = min($remaining_principal, $credit_schedule->INTEREST);
            } else {
                // If angsuran is less than the principal
                $payment_value_principal = $byr_angsuran;
                $payment_value_interest = $byr_angsuran; // No payment towards interest
            }

            // Add to principal if it's not fully paid
            $new_payment_value_principal = ($getPrincipal > $valBeforePrincipal)
                ? $valBeforePrincipal + $payment_value_principal
                : $valBeforePrincipal;

            // Add to interest if it's not fully paid
            $new_payment_value_interest = ($getInterest > $valBeforeInterest)
                ? $valBeforeInterest + $payment_value_interest
                : $valBeforeInterest;

            // Calculate insufficient payment
            $total_paid = $new_payment_value_principal + $new_payment_value_interest;

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                                    ? ($total_paid - $credit_schedule->INSTALLMENT)
                                    : 0;

            $credit_schedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $new_payment_value_principal,
                'PAYMENT_VALUE_INTEREST' => $new_payment_value_interest,
                'INSUFFICIENT_PAYMENT' => $insufficient_payment,
                'PAYMENT_VALUE' => $payment_value,
                'PAID_FLAG' => $payment_value == $credit_schedule->INSTALLMENT ? 'PAID' : ''
            ]);
        }

    }

    private function updateArrears($loan_number, $tgl_angsuran, $bayar_denda)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        if ($check_arrears) {
            $check_arrears->update([
                'PAID_PENALTY' => $bayar_denda
            ]);
        }
    }

    private function saveKwitansi($request, $customer_detail, $no_inv, $created_now)
    {
        $save_kwitansi = [
            "PAYMENT_TYPE" => 'angsuran',
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->no_facility ?? null,
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

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $created_now)
    {
        $uid = Uuid::uuid7()->toString();

        $payment_record = [
            'ID' => $uid,
            'ACC_KEY' => $request->pembayaran,
            'STTS_RCRD' => $request->payment_method == 'cash' ? 'PAID' : 'PENDING',
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid,
            'PAYMENT_METHOD' => $request->payment_method,
            'BRANCH' => $branch->CODE_NUMBER,
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => $created_now,
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => $res['total_bayar'],
            'OS_AMOUNT' => 0,
            'START_DATE' => $tgl_angsuran,
            'AUTH_BY' => $request->user()->id,
            'AUTH_DATE' => $created_now,
            'BANK_NAME' => $request->nama_bank ?? null,
            'BANK_ACC_NUMBER' => $request->no_rekening ?? null
        ];

        M_Payment::create($payment_record);

        // Payment principal
        $principal_amount = $res['principal'];
        $angsuran_amount = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        // Payment principal
        $data_principal = self::preparePaymentData($uid,'ANGSURAN POKOK',$principal_amount);
        M_PaymentDetail::create($data_principal);

        // Payment interest
        $interest_amount = ($angsuran_amount >= $principal_amount) ? ($angsuran_amount - $principal_amount) : 0;
        $data_interest = self::preparePaymentData($uid, 'BUNGA PINJAMAN', $interest_amount);
        M_PaymentDetail::create($data_interest);

        // Payment denda
        if ($bayar_denda !== 0) {
            $data_denda = self::preparePaymentData($uid,'DENDA PINJAMAN', $bayar_denda);
            M_PaymentDetail::create($data_denda);
        }

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($check_credit) {
            $check_credit->update([
                'PAID_PRINCIPAL' => $check_credit->PAID_PRINCIPAL + $principal_amount,
                'PAID_INTEREST' => $check_credit->PAID_INTEREST + $interest_amount,
                'PAID_PINALTY' => $bayar_denda !== 0 ? $check_credit->PAID_PINALTY + $bayar_denda:0
            ]);
        }
    }

    function preparePaymentData($payment_id,$acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount,
            'OS_AMOUNT' => 0
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
            $payment = M_Payment::where('INVOICE', $request->no_invoice)->get();
            $check = M_KwitansiStructurDetail::where('no_invoice', $request->no_invoice)->get();

            if($check->isEmpty()){
                throw new Exception('Invoice Number Not Found');
            }
            
            if ($payment) {
                foreach ($payment as $payments) {
                    if($request->flag == 'yes'){
                        $payments->update([
                            'STTS_RCRD' => 'PAID',
                        ]);
                    }else{
                        $payments->update([
                            'STTS_RCRD' => 'CANCEL',
                        ]);
                    }
                }
            }

            if($request->flag == 'yes'){
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
            }else{

                foreach ($check as $res) {

                    $loan_number = $res['loan_number'];
                    $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                    $credit_schedule = M_CreditSchedule::where([
                        'LOAN_NUMBER' => $loan_number,
                        'PAYMENT_DATE' => $tgl_angsuran
                    ])->first();

                    if ($credit_schedule) {
                        $credit_schedule->update([
                            'PAYMENT_VALUE' => "",
                            'PAID_FLAG' => ''
                        ]);
                    }
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

            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    } 
}

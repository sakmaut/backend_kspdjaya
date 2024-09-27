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
   
    public function paymentPelunasan(Request $request)
    {
        DB::beginTransaction();
        try {
            DB::commit();

            $loan_number = $request->LOAN_NUMBER;
            $uid = Uuid::uuid7()->toString();
            $created_now = Carbon::now();
            $no_inv = generateCode($request, 'payment', 'INVOICE', 'INV');
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            $check = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->first();

            if (!$check) {
                throw new Exception('Loan Number Not Exist');
            }

            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();
            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            $save_kwitansi = [
                "NO_TRANSAKSI" => $no_inv,
                "LOAN_NUMBER" => $request->LOAN_NUMBER ?? null,
                "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
                'CUST_CODE' => $detail_customer->CUST_CODE,
                'NAMA' => $detail_customer->NAME,
                'ALAMAT' => $detail_customer->ADDRESS,
                'RT' => $detail_customer->RT,
                'RW' => $detail_customer->RW,
                'PROVINSI' => $detail_customer->PROVINCE,
                'KOTA' => $detail_customer->CITY,
                'KELURAHAN' => $detail_customer->KELURAHAN,
                'KECAMATAN' => $detail_customer->KECAMATAN,
                "METODE_PEMBAYARAN" => $request->METODE_PEMBAYARAN ?? null,
                "TOTAL_BAYAR" => $request->TOTAL_BAYAR ?? null,
                "PEMBULATAN" => $request->PEMBULATAN ?? null,
                "KEMBALIAN" => $request->KEMBALIAN ?? null,
                "JUMLAH_UANG" => $request->UANG_PELANGGAN ?? null,
                "NAMA_BANK" => $request->NAMA_BANK ?? null,
                "NO_REKENING" => $request->NO_REKENING ?? null,
                "CREATED_BY" => $request->user()->fullname
            ];

            M_Kwitansi::create($save_kwitansi);

            $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            // Check if all discount values are non-zero
            $allDiscountsPaid = collect($discounts)->every(fn ($value) => $value != 0);

            // Check if the payment method is cash
            $checkMethodPayment = strtolower($request->METODE_PEMBAYARAN) === 'cash';

            // Determine the status
            $status = $allDiscountsPaid ? 'PAID' : 'PENDING';

            // If payment method is cash, set status to PENDING regardless of discounts
            if ($checkMethodPayment) {
                $status = 'PENDING';
            }

            $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $request->LOAN_NUMBER)
                                                ->whereNull('PAID_FLAG')
                                                ->get();

            $installmentCounts = $creditSchedule->map(function ($item) {
                return $item->INSTALLMENT_COUNT;
            })->join(',');

            $payment_record = [
                'ID' => $uid,
                'ACC_KEY' => 'pelunasan',
                'STTS_RCRD' => $status,
                'INVOICE' => $no_inv,
                'NO_TRX' => $request->uid ?? null,
                'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN,
                'BRANCH' => $getCodeBranch->CODE_NUMBER,
                'LOAN_NUM' => $request->LOAN_NUMBER ?? null,
                'VALUE_DATE' => null,
                'ENTRY_DATE' => $created_now,
                'TITLE' => 'Angsuran Ke-' . $installmentCounts,
                'ORIGINAL_AMOUNT' => $request->TOTAL_BAYAR,
                'OS_AMOUNT' => 0,
                'START_DATE' => null,
                'AUTH_BY' => $request->user()->id,
                'AUTH_DATE' => $created_now
            ];

            M_Payment::create($payment_record);

            $payments = [
                'BAYAR_POKOK'   => 'PELUNASAN POKOK',
                'BAYAR_BUNGA'   => 'BAYAR PELUNASAN BUNGA',
                'BAYAR_PINALTI' => 'BAYAR PELUNASAN PINALTY',
                'BAYAR_DENDA'   => 'BAYAR PELUNASAN DENDA'
            ];

            $discounts = [
                    'DISKON_POKOK'   => 'DISKON POKOK',
                    'DISKON_BUNGA'   => 'DISKON BUNGA',
                    'DISKON_PINALTI' => 'DISKON PINALTY',
                    'DISKON_DENDA'   => 'DISKON DENDA'
                ];

            // Handle payments
            foreach ($payments as $key => $description) {
                if ($request->$key != 0) {
                    $data = self::preparePaymentData($uid, $description, $request->$key);
                    M_PaymentDetail::create($data);
                }
            }

            // Handle discounts
            foreach ($discounts as $key => $description) {
                if ($request->$key != 0) {
                    $data = self::preparePaymentData($uid, $description, $request->$key);
                    M_PaymentDetail::create($data);
                }
            }


            

            // if ($check_credit) {
            //     $check_credit->update([
            //         'PAID_PRINCIPAL' => $request->BAYAR_POKOK,
            //         'PAID_INTEREST' => $request->BAYAR_BUNGA,
            //         'PAID_PINALTY' => $request->PAID_PINALTY
            //     ]);
            // }

           

            // $bayarPokok = $request->input('BAYAR_POKOK');
            // $bayarBunga = $request->input('BAYAR_BUNGA');

            // foreach ($creditSchedule as $res) {
            //     if ($bayarPokok > 0) {
            //         if ($bayarPokok >= $res['PRINCIPAL']) {
            //             $payment_value_principal = $res['PRINCIPAL'];
            //             $bayarPokok -= $res['PRINCIPAL'];
            //         } else {
            //             $payment_value_principal = $bayarPokok;
            //             $bayarPokok = 0;
            //         }
            //     } else {
            //         $payment_value_principal = 0;
            //     }

            //     // Calculate for interest
            //     if ($bayarBunga > 0) {
            //         if ($bayarBunga >= $res['INTEREST']) {
            //             $payment_value_interest = $res['INTEREST'];
            //             $bayarBunga -= $res['INTEREST'];
            //         } else {
            //             $payment_value_interest = $bayarBunga;
            //             $bayarBunga = 0;
            //         }
            //     } else {
            //         $payment_value_interest = 0;
            //     }

            //     // Total payment value (principal + interest)
            //     $payment_value = $payment_value_principal + $payment_value_interest;

            //     // Check if the installment is fully paid
            //     $isPaid = $payment_value == $res['INSTALLMENT'] ? 'PAID' : '';

            //     // Update the credit schedule record
            //     $res->update([
            //         'PAYMENT_VALUE_PRINCIPAL' => $payment_value_principal,
            //         'PAYMENT_VALUE_INTEREST' => $payment_value_interest,
            //         'PAYMENT_VALUE' => $payment_value,
            //         'PAID_FLAG' => $isPaid
            //     ]);

            //     // Break the loop if both `BAYAR_POKOK` and `BAYAR_BUNGA` are fully used
            //     if ($bayarPokok <= 0 && $bayarBunga <= 0) {
            //         break;
            //     }
              
            // }
        

            $response = [
                "no_transaksi" => $no_inv,
                'cust_code' => $detail_customer->CUST_CODE,
                'nama' => $detail_customer->NAME,
                'alamat' => $detail_customer->ADDRESS,
                'rt' => $detail_customer->RT,
                'rw' => $detail_customer->RW,
                'provinsi' => $detail_customer->PROVINCE,
                'kota' => $detail_customer->CITY,
                'kelurahan' => $detail_customer->KELURAHAN,
                'kecamatan' => $detail_customer->KECAMATAN,
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
                "created_at" => Carbon::parse($created_now)->format('d-m-Y')
            ];
    
            return response()->json($response, 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
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
            'bayar_angsuran' => $res,
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
            $payment_value_principal = $res['principal'];
            $remaining_amount = $res['bayar_angsuran'] - $payment_value_principal;
            $payment_value_interest = $remaining_amount > $res['interest'] ? $res['interest'] : $remaining_amount;
            $payment_value = $res['bayar_angsuran'];

            $credit_schedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $payment_value_principal,
                'PAYMENT_VALUE_INTEREST' => $payment_value_interest,
                'PAYMENT_VALUE' => $payment_value,
                'PAID_FLAG' => $res['bayar_angsuran'] == $credit_schedule->INSTALLMENT ? 'PAID' : ''
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
            'ORIGINAL_AMOUNT' => ($res['bayar_angsuran']+ $res['bayar_denda']),
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

    public function pelunasan(Request $request){
        try {

            $loan_number = $request->loan_number;

            $result = DB::select(
                "select	(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as SISA_POKOK,
                        c.BUNGA as BUNGA_BERJALAN,
                        b.INT_ARR as TUNGGAKAN_BUNGA,
                        b.DENDA as DENDA,
                        (coalesce(a.PENALTY_RATE,3)/100)*(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as PINALTI
                from	credit a
                        left join (	select	LOAN_NUMBER, 
                                            sum(PAST_DUE_INTRST)-sum(PAID_INT) as INT_ARR, 
                                            sum(PAST_DUE_PENALTY)-sum(PAID_PENALTY) as DENDA
                                    from	arrears
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and STATUS_REC = 'A'
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
                where a.LOAN_NUMBER = '{$loan_number}'"
            );

            $processedResults = array_map(function ($item) {
                return [
                    'SISA_POKOK' => intval($item->SISA_POKOK),
                    'BUNGA_BERJALAN' => intval($item->BUNGA_BERJALAN),
                    'TUNGGAKAN_BUNGA' => intval($item->TUNGGAKAN_BUNGA),
                    'DENDA' => intval($item->DENDA),
                    'PINALTI' => intval($item->PINALTI),
                ];
            }, $result);
    
            return response()->json($processedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

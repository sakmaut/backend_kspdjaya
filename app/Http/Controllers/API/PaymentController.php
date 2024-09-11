<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use App\Models\M_Payment;
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
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {   
            $getCodeBranch = M_Branch::find($request->user()->branch_id);
            $created_now = Carbon::now();
            $no_inv = generateCode($request, 'payment', 'INVOICE','INV');
            $paymentAmount = $request->nilai_pembayaran;

            // $pembayaran = [];
            // foreach ($schedule as $scheduleItem) {
            //     if ($paymentAmount > 0) {
            //         $installment = $scheduleItem->INSTALLMENT;
            //         $remainingPayment = $installment - $scheduleItem->PAYMENT_VALUE;
            
            //         if ($remainingPayment > 0) {
            //             $paymentValue = min($paymentAmount, $remainingPayment);
            //             $paymentAmount -= $paymentValue;
            //             $scheduleItem->PAYMENT_VALUE += $paymentValue;
            
            //             if ($scheduleItem->PAYMENT_VALUE == $installment) {
            //                 $scheduleItem->PAID_FLAG = 'PAID';
            //             }
            //         } else {
            //             continue;
            //         }

            //         $pembayaran[] = [
            //             'installment' => $scheduleItem->INSTALLMENT_COUNT,
            //             'payment_value' => $paymentValue,
            //             'original_amount' => $request->nilai_pembayaran,
            //             'title' => 'Angsuran Ke-'.$scheduleItem->INSTALLMENT_COUNT,
            //         ];

            //         $arrayData = [  
            //             'ID' => Uuid::uuid7()->toString(),
            //             'STTS_RCRD' => 'A',
            //             'INVOICE' => $no_inv,
            //             'NO_TRX' => generateCode($request, 'payment', 'NO_TRX','TRX'),
            //             'PAYMENT_METHOD' => $request->payment_method,
            //             'BRANCH' => $getCodeBranch->CODE_NUMBER,
            //             'LOAN_NUM' => $request->loan_number,
            //             'VALUE_DATE' => $request->tgl_bayar,
            //             'ENTRY_DATE' => $created_now,
            //             'TITLE' => 'Angsuran Ke-'.$scheduleItem->INSTALLMENT_COUNT,
            //             'ORIGINAL_AMOUNT' => $request->nilai_pembayaran,
            //             'OS_AMOUNT' => 0,
            //             'AUTH_BY' => $request->user()->id,
            //             'AUTH_DATE' => $created_now,
            //             'BANK_NAME' => $request->nama_bank??null,
            //             'BANK_ACC_NUMBER' => $request->no_rekening??null,
            //         ];

            //         // if($paymentAmount != 0){
            //         //     M_Payment::create($arrayData);
            //         // }
                   
            //         // $scheduleItem->save();
            //     } else {
            //         break;
            //     }
            // }

            $customer_detail=[];
            if (isset($request->struktur) && is_array($request->struktur)) {
               foreach ($request->struktur as $res) {

                    $loan_number = $res['loan_number'];
                    $tgl_agsuran =  Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                    $credit = M_Credit::where('LOAN_NUMBER',$loan_number)->first();
                    $detail_customer = M_Customer::where('CUST_CODE',$credit->CUST_CODE)->first();

                    $check_arrears = M_Arrears::where(['LOAN_NUMBER' => $loan_number,'START_DATE' => $tgl_agsuran])->first();
                    if($check_arrears){
                        $check_arrears->update([
                            'PAID_PENALTY' => $res['bayar_denda']
                        ]);
                    }

                    $customer_detail = [
                        'cust_code' =>  $detail_customer->CUST_CODE,
                        'nama' => $detail_customer->NAME,
                        'alamat' => $detail_customer->ADDRESS,
                        'rt' => $detail_customer->RT,
                        'rw' => $detail_customer->RW,
                        'provinsi' => $detail_customer->PROVINCE,
                        'kota' => $detail_customer->CITY,
                        'kelurahan' => $detail_customer->KELURAHAN,
                        'kecamatan' => $detail_customer->KECAMATAN,
                    ];

                    $data = [  
                        'ID' => Uuid::uuid7()->toString(),
                        'STTS_RCRD' => 'A',
                        'INVOICE' => $no_inv,
                        'NO_TRX' => generateCode($request, 'payment', 'NO_TRX','TRX'),
                        'PAYMENT_METHOD' => $request->payment_method,
                        'BRANCH' => $getCodeBranch->CODE_NUMBER,
                        'LOAN_NUM' => $loan_number,
                        'VALUE_DATE' => null,
                        'ENTRY_DATE' => $created_now,
                        'TITLE' => 'Angsuran Ke-'.$res['angsuran_ke'],
                        'ORIGINAL_AMOUNT' => $res['bayar_angsuran']+ $res['bayar_denda'],
                        'OS_AMOUNT' => 0,
                        'START_DATE' => $tgl_agsuran,
                        'AUTH_BY' => $request->user()->id,
                        'AUTH_DATE' => $created_now,
                        'BANK_NAME' => $request->nama_bank??null,
                        'BANK_ACC_NUMBER' => $request->no_rekening??null,
                    ];

                    M_Payment::create($data);
               }
            }

            $build = [
                "no_transaksi" => $no_inv,
                "detail_pelanggan" => $customer_detail,
                "tgl_transaksi" => Carbon::now()->format('d-m-Y'),
                "payment_method" => $request->payment_method,
                "nama_bank" => $request->nama_bank,
                "no_rekening" => $request->no_rekening,
                "bukti_transfer" => '',
                // "pembayaran" => $pembayaran,
                "pembulatan" => $request->pembulatan,
                "kembalian" => $request->kembalian,
                "jml_pembayaran" => $request->nilai_pembayaran,
                "terbilang" => bilangan($request->nilai_pembayaran)??null,
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
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CreditList;
use App\Http\Resources\R_CustomerSearch;
use App\Models\M_Arrears;
use App\Models\M_CrCollateral;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_Customer::all()->map(function ($customer) {
                $credit = M_Credit::where('CUST_CODE',$customer->CUST_CODE)->first();
                $customer->jaminan = M_CrCollateral::where('CR_CREDIT_ID', $credit->ID)->first();
                return $customer;
            });

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function searchCustomer(Request $request)
    {
        try {
            $searchParams = [
                'nama' => 't1.NAME',
                'no_kontrak' => 't0.LOAN_NUMBER',
                'no_polisi' => 't2.POLICE_NUMBER',
            ];
            
            // Check if all search parameters are null
            if (array_reduce(array_keys($searchParams), function ($carry, $param) use ($request) {
                return $carry && is_null($request->$param);
            }, true)) {
                return [];
            }
            
            $query = DB::table('credit as t0')
                ->select('t0.LOAN_NUMBER', 't0.INSTALLMENT', 't1.NAME', 't1.ADDRESS', 't2.POLICE_NUMBER', 't0.ORDER_NUMBER')
                ->join('customer as t1', 't1.CUST_CODE', '=', 't0.CUST_CODE')
                ->join('cr_collateral as t2', 't2.CR_CREDIT_ID', '=', 't0.ID');
            
            foreach ($searchParams as $param => $column) {
                if ($request->$param) {
                    $query->where($column, 'like', '%' . $request->$param . '%');
                }
            }
            
            $results = $query->get();
            
    
            $dto = R_CustomerSearch::collection($results);

            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function fasilitas(Request $request)
    {
        try {
            $data =  M_Credit::where(['CUST_CODE' => $request->cust_code,'STATUS' =>'A'])->get();

            if ($data->isEmpty()) {
                throw new Exception("Cust Code Is Not Exist");
            }

            $dto = R_CreditList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function creditStruktur(Request $request)
    {
        try {
            $schedule = [];

            if (isset($request->jumlah_uang)) {
                $data = M_CreditSchedule::where('loan_number',$request->loan_number)->get();
              
                $paymentAmount = $request->jumlah_uang;
            
                foreach ($data as $scheduleItem) {
                    $initialPaymentValue = $scheduleItem->PAYMENT_VALUE;
                    $arrears = M_Arrears::where(['LOAN_NUMBER' => $scheduleItem->LOAN_NUMBER,'START_DATE' => $scheduleItem->PAYMENT_DATE])->first();

                    if ($paymentAmount > 0) {
                        $installment = $scheduleItem->INSTALLMENT;
                        $remainingPayment = $installment - $scheduleItem->PAYMENT_VALUE;
                
                        if ($remainingPayment > 0) {
                            $paymentValue = min($paymentAmount, $remainingPayment);
                            $paymentAmount -= $paymentValue + ($arrears->PAST_DUE_PENALTY ?? 0);
                            $scheduleItem->PAYMENT_VALUE += $paymentValue;

                            // Add penalty (denda) to both schedule items
                            $scheduleItem->PAYMENT_VALUE += $arrears->PAST_DUE_PENALTY ?? 0;
                
                            if ($scheduleItem->PAYMENT_VALUE == $installment) {
                                $scheduleItem->PAID_FLAG = 'PAID';
                            }
                        } else {
                            continue;
                        }
                    }
                  
                    $after_value = intval($scheduleItem->PAYMENT_VALUE -  $initialPaymentValue);

                    $schedule[] = [
                        'id_structur' => $scheduleItem->INSTALLMENT_COUNT.'-'. $after_value,
                        'angsuran_ke' => $scheduleItem->INSTALLMENT_COUNT,
                        'loan_number' => $scheduleItem->LOAN_NUMBER,
                        'tgl_angsuran' => Carbon::parse($scheduleItem->PAYMENT_DATE)->format('d-m-Y'),
                        'principal' => number_format($scheduleItem->PRINCIPAL, 2),
                        'interest' => number_format($scheduleItem->INTEREST, 2),
                        'installment' => number_format($scheduleItem->INSTALLMENT, 2),
                        'principal_remains' => number_format($scheduleItem->PRINCIPAL_REMAINS, 2),
                        'before_payment' =>  number_format($initialPaymentValue, 2),
                        'after_payment' =>  $after_value,
                        'payment' => number_format($scheduleItem->PAYMENT_VALUE, 2),
                        'flag' => $scheduleItem->PAID_FLAG,
                        'denda' => intval($arrears->PAST_DUE_PENALTY??null)
                    ];
                }

                // $paymentFor = [];
                // foreach ($schedule as $key => $value) {
                //     if ($value['flag'] == 'PAID') {
                //         $paymentFor[] = [
                //             'angsuran_ke' => $value['angsuran_ke'],
                //             'loan_number' => $value['loan_number'],
                //             'tgl_angsuran' => $value['tgl_angsuran'],
                //             'principal' => $value['principal'],
                //             'interest' => $value['interest'],
                //             'installment' => $value['installment'],
                //             'principal_remains' => $value['principal_remains'],
                //             'payment' => $value['payment'],
                //             'flag' => $value['flag']
                //         ];
                //     }
                // }

                // foreach ($schedule as $key => $value) {
                //     $schedule['payment_for'] = $paymentFor;
                // }
            }else{
                $data = M_CreditSchedule::where('LOAN_NUMBER', $request->loan_number)
                ->where(function ($query) {
                    $query->whereNull('PAID_FLAG')
                        ->orWhere('PAID_FLAG', '<>', 'PAID');
                })
                ->get();

                if ($data->isEmpty()) {
                    throw new Exception("Loan Number Is Not Exist");
                }

                foreach ($data as $res) {
                    $arrears = M_Arrears::where(['LOAN_NUMBER' => $res->LOAN_NUMBER,'START_DATE' => $res->PAYMENT_DATE])->first();

                    $schedule[]=[
                        'angsuran_ke' =>  $res->INSTALLMENT_COUNT,
                        'loan_number' => $res->LOAN_NUMBER,
                        'tgl_angsuran' => Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y'),
                        'principal' => number_format($res->PRINCIPAL, 2),
                        'interest' => number_format($res->INTEREST, 2),
                        'installment' => number_format($res->INSTALLMENT, 2),
                        'principal_remains' => number_format($res->PRINCIPAL_REMAINS, 2),
                        'payment' => number_format($res->PAYMENT_VALUE, 2),
                        'flag' => $res->PAID_FLAG,
                        'denda' =>intval($arrears->PAST_DUE_PENALTY??null)
                    ];
                }
            }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cekRO(Request $request)
    {
        try {
            $data = M_Customer::where('ID_NUMBER',$request->no_ktp)->get();

            if($data->isNotEmpty()){
                $datas = [];
                foreach($data as $customer) {
                    $datas[] = [
                        'no_ktp' => $customer->ID_NUMBER,
                        'no_kk' => $customer->KK_NUMBER,
                        'nama' => $customer->NAME,
                        'tgl_lahir' => $customer->BIRTHDATE,
                        'alamat' => $customer->ADDRESS,
                        'rw' => $customer->RW,
                        'rt' => $customer->RT,
                        'no_hp' => $customer->PHONE_PERSONAL
                    ];
                }
            }else{
                $datas =[];
            }

           
            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

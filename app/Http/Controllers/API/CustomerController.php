<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CreditList;
use App\Models\M_CrCollateral;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use Exception;
use Illuminate\Http\Request;

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
                    if ($paymentAmount > 0) {
                        $installment = $scheduleItem->INSTALLMENT;
                        $remainingPayment = $installment - $scheduleItem->PAYMENT_VALUE;
                
                        if ($remainingPayment > 0) {
                            $paymentValue = min($paymentAmount, $remainingPayment);
                            $paymentAmount -= $paymentValue;
                            $scheduleItem->PAYMENT_VALUE += $paymentValue;
                
                            if ($scheduleItem->PAYMENT_VALUE == $installment) {
                                $scheduleItem->PAID_FLAG = 'PAID';
                            }
                        } else {
                            continue;
                        }
                    }

                    $schedule['list_structur'][] = [
                        'angsuran_ke' => $scheduleItem->INSTALLMENT_COUNT,
                        'loan_number' => $scheduleItem->LOAN_NUMBER,
                        'tgl_angsuran' => $scheduleItem->PAYMENT_DATE,
                        'principal' => number_format($scheduleItem->PRINCIPAL, 2),
                        'interest' => number_format($scheduleItem->INTEREST, 2),
                        'installment' => number_format($scheduleItem->INSTALLMENT, 2),
                        'principal_remains' => number_format($scheduleItem->PRINCIPAL_REMAINS, 2),
                        'before_payment' =>  number_format($initialPaymentValue, 2),
                        'after_payment' => number_format($scheduleItem->PAYMENT_VALUE -  $initialPaymentValue, 2),
                        'total_payment' => number_format($scheduleItem->PAYMENT_VALUE, 2),
                        'flag' => $scheduleItem->PAID_FLAG
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
                    $schedule[]=[
                        'angsuran_ke' =>  $res->INSTALLMENT_COUNT,
                        'loan_number' => $res->LOAN_NUMBER,
                        'tgl_angsuran' => $res->PAYMENT_DATE,
                        'principal' => number_format($res->PRINCIPAL, 2),
                        'interest' => number_format($res->INTEREST, 2),
                        'installment' => number_format($res->INSTALLMENT, 2),
                        'principal_remains' => number_format($res->PRINCIPAL_REMAINS, 2),
                        'payment' => number_format($res->PAYMENT_VALUE, 2),
                        'flag' => $res->PAID_FLAG
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

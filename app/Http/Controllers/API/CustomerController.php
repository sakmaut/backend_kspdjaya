<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CreditList;
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
            $data =  M_Customer::all();

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
            $data =  M_Credit::where('CUST_CODE',$request->cust_code)->get();

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
            $data = M_CreditSchedule::where('loan_number',$request->loan_number)->get();

            if ($data->isEmpty()) {
                throw new Exception("Loan Number Is Not Exist");
            }

            return response()->json($data, 200);
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
                        'name' => $customer->NAME,
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

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Arrears;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function inquiryList(Request $request)
    {
        try {
            $results = DB::table('credit as a')
                            ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                            ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                            ->leftJoin('branch as d', 'd.ID', '=', 'a.BRANCH')
                            ->select(   'a.ID as creditId',
                                        'a.ORDER_NUMBER', 
                                        'b.ID as custId', 
                                        'b.CUST_CODE', 
                                        'b.NAME as customer_name',
                                        'c.POLICE_NUMBER', 
                                        'a.INSTALLMENT_DATE', 
                                        'd.NAME as branch_name')
                            ->orderBy('a.ORDER_NUMBER', 'asc')
                            ->get();

            $mapping = $results->map(function($list){
                return [
                    'credit_id' => $list->creditId,
                    'order_number' => $list->ORDER_NUMBER,
                    'cust_id' => $list->custId,
                    'cust_code' => $list->CUST_CODE,
                    'customer_name' => $list->customer_name,
                    'police_number' => $list->POLICE_NUMBER,
                    'entry_date' => date('Y-m-d',strtotime($list->INSTALLMENT_DATE)),
                    'branch_name' => $list->branch_name,
                ];
            });

            return response()->json($mapping, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pinjaman(Request $request,$id)
    {
        try {
            $results = M_Credit::where('ID',$id)->first();

            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function debitur(Request $request,$id)
    {
        try {

            $results = DB::table('customer as a')
                        ->leftJoin('customer_extra as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                        ->select('a.*', 'b.*')
                        ->where('a.ID', $id)
                        ->first();

            if(!$results){
                $results = [];
            }

            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function jaminan(Request $request,$id)
    {
        try {
            $results = M_CrCollateral::where('CR_CREDIT_ID',$id)->get();
            $results2 = M_CrCollateralSertification::where('CR_CREDIT_ID',$id)->get();

            $results = $results->map(function ($item) {
                $item->COLLATERAL_TYPE = 'kendaraan';
                return $item;
            });
        
            $results2 = $results2->map(function ($item) {
                $item->COLLATERAL_TYPE = 'sertifikat';
                return $item;
            });
            
            $mergedResults = $results->merge($results2);

            return response()->json($mergedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pembayaran(Request $request)
    {
        try {
            $results = M_Payment::all();
           
            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function tunggakkan(Request $request)
    {
        try {
            $results = M_Arrears::all();
           
            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

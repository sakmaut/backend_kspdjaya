<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_AdminFee;
use App\Models\M_AdminType;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFeeController extends Controller
{

    public function index(Request $request)
    {
        try {
            $data = M_AdminFee::with('links')->get();

            $build = [];
        
            foreach ($data as $value) {
              
                $struktur = [];

                foreach ($value->links as $link) {
                    $struktur[] = [
                        'fee_name' => $link['fee_name'],
                        '6_month' => $link['6_month'],
                        '12_month' => $link['12_month'],
                        '18_month' => $link['18_month'],
                        '24_month' => $link['24_month'],
                    ];
                }

                $tenors = ['6', '12', '18', '24'];
                $strukturTenors = [];

                foreach ($tenors as $tenor) {
                    $tenorData = ['tenor' => (int) $tenor];
                    foreach ($struktur as $s) {
                        $tenorData[$s['fee_name']] = (float) $s[$tenor . '_month'];
                    }
                    $strukturTenors['tenor_'. $tenor] = $tenorData;
                }

                $build[] = [
                    'tipe' => $value->category,
                    'range_start' => (float) $value->start_value,
                    'range_end' =>(float) $value->end_value,
                    'tenor_6' =>$strukturTenors['tenor_6'],
                    'tenor_12' =>$strukturTenors['tenor_12'],
                    'tenor_18' =>$strukturTenors['tenor_18'],
                    'tenor_24' =>$strukturTenors['tenor_24']
                ];
            }            

            return response()->json($build, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $data_admin_fee =[
                'category' => $request->kategory,
                'start_value' => $request->start_value,
                'end_value' => $request->end_value
            ];

            $admin_fee_id = M_AdminFee::create($data_admin_fee);

            if(isset($request->struktur) && is_array($request->struktur)){
                foreach ($request->struktur as $value) {
                    $data_admin_type = [
                        'admin_fee_id' => $admin_fee_id->id,
                        'fee_name' => isset($value['key'])?$value['key']:'',
                        '6_month' => $value['tenor6'],
                        '12_month' => $value['tenor12'],
                        '18_month' => $value['tenor18'],
                        '24_month' => $value['tenor24']
                    ];
                   
                    M_AdminType::create($data_admin_type);
                }
            }

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

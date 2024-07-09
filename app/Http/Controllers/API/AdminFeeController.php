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
                    $tenorData = ['tenor' => $tenor];
                    foreach ($struktur as $s) {
                        $tenorData[$s['fee_name']] = $s[$tenor . '_month'];
                    }
                    $strukturTenors[] = $tenorData;
                }

                $build[] = [
                    'tipe' => $value->category,
                    'range' =>'Rp. '. number_format($value->start_value) . ' - ' .number_format( $value->end_value),
                    'struktur' => $strukturTenors
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
                        'fee_name' => $value['fee_name'],
                        '6_month' => $value['6_month'],
                        '12_month' => $value['12_month'],
                        '18_month' => $value['18_month'],
                        '24_month' => $value['24_month']
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

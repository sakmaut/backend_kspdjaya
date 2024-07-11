<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_AdminFee;
use App\Models\M_AdminType;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFeeController extends Controller
{

    public function index(Request $request)
    {
        try {
            $data = M_AdminFee::with('links')->get();
            $show = $this->buildArray($data);

            return response()->json($show, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $request,$id)
    {
        try {
            $data = M_AdminFee::with('links')->where('id',$id)->get();

            if ($data->isEmpty()) {
                throw new Exception("Data Not Found", 404);
            }

            $show = $this->buildArray($data);
    
            return response()->json($show, 200);
        } catch (Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $checkRange =   M_AdminFee::where('category', 'bulanan')
                            ->where(function ($query) use ($request) {
                                $query->where(function ($q) use ($request) {
                                    $q->where('start_value', '<', $request->start_value)
                                    ->where('end_value', '>', $request->start_value);
                                })->orWhere(function ($q) use ($request) {
                                    $q->where('start_value', '<', $request->end_value)
                                    ->where('end_value', '>', $request->end_value);
                                });
                            })->get();

            if (!$checkRange->isEmpty()) {
                throw new Exception("Data Range Sudah Ada");
            }

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
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {

            $admin_fee = M_AdminFee::find($id);

            if (!$admin_fee) {
                throw new Exception("Data Not Found", 404);
            }

            $data_admin_fee =[
                'category' => $request->kategory,
                'start_value' => $request->start_value,
                'end_value' => $request->end_value
            ];

            $admin_fee->update($data_admin_fee);

            if (M_AdminType::where('admin_fee_id', $id)->exists()) {
                M_AdminType::where('admin_fee_id', $id)->delete();
            }

            if(isset($request->struktur) && is_array($request->struktur)){
                foreach ($request->struktur as $value) {
                    $data_admin_type = [
                        'admin_fee_id' => $id,
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
            return response()->json(['message' => 'update successfully'], 200);
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

    public function buildArray($data){
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
                $total = 0;
            
                foreach ($struktur as $s) {
                    $feeName = $s['fee_name'];
                    $feeValue = (float) $s[$tenor . '_month'];
                    $tenorData[$feeName] = $feeValue;
            
                    if ($feeName !== 'eff_rate') {
                        $total += $feeValue;
                    }
                }
            
                $tenorData['total'] = $total;
                $strukturTenors['tenor_' . $tenor] = $tenorData;
            }
            

            $build[] = [
                'id' => $value->id,
                'tipe' => $value->category,
                'range_start' => (float) $value->start_value,
                'range_end' =>(float) $value->end_value,
                'tenor_6' =>$strukturTenors['tenor_6'],
                'tenor_12' =>$strukturTenors['tenor_12'],
                'tenor_18' =>$strukturTenors['tenor_18'],
                'tenor_24' =>$strukturTenors['tenor_24']
            ];
        }   
        
        return $build;
    }

    public function fee(Request $request)
    {
        try {
            $plafond = (int) $request->plafond / 1000000; 
            $angsuran_type = $request->jenis_angsuran;

            $adminFee = M_AdminFee::with('links') 
                    ->whereRaw("start_value <= $plafond and end_value >= $plafond")
                    ->where('category', $angsuran_type)
                    ->get();

            $show = $this->buildArray($adminFee);
    
            return response()->json($show, 200);
        } catch (Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
    
}

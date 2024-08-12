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
    protected $adminfee;

    public function __construct(M_AdminFee $admin_fee)
    {
        $this->adminfee = $admin_fee;
    }

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

    public function fee_survey(Request $request)
    {
        try {
            $plafond = (int) $request->plafond / 1000000; 
            $angsuran_type = $request->jenis_angsuran;
        
            if($plafond == null || $plafond == 0 || empty($plafond)){
                $adminFee = M_AdminFee::with('links')->get();
            }else{
                $adminFee =$this->adminfee->checkRange($plafond,$angsuran_type);
            }

            $show = $this->buildArray($adminFee,
            [
                'returnSingle' => true,
                'plafond' => $request->plafond,
                'angsuran_type' => $angsuran_type
            ]);
    
            return response()->json($show, 200);
        } catch (Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fee(Request $request)
    {
        try {
            $plafond = (int) $request->plafond / 1000000; 
            $angsuran_type = $request->jenis_angsuran;
            $tenor = $request->tenor;

            $adminFee =$this->adminfee->checkRange($plafond,$angsuran_type);

            $show = $this->buildArray($adminFee,
            [   'returnSingle' => true,
                'tenor' => $tenor,
                'angsuran_type' => $angsuran_type,
                'plafond' => $request->plafond,
            ]);
    
            return response()->json($show, 200);
        } catch (Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function buildArray($data, $options = [])
    {
        $returnSingle = $options['returnSingle'] ?? false;
        $specificTenor = $options['tenor'] ?? null;
        $plafond = $options['plafond'] ?? null;
        $angsuran_type = $options['angsuran_type'] ?? null;

        $build = [];

        foreach ($data as $value) {
            $strukturTenors = $this->buildStrukturTenors($value->links, $specificTenor,$plafond,$angsuran_type);
            
            $item = [
                'id' => $value->id,
                'tipe' => $value->category,
                'range_start' => (float) $value->start_value,
                'range_end' => (float) $value->end_value,
            ];

            if ($specificTenor) {
                $item += $strukturTenors["tenor_$specificTenor"];
            } else {
                $item += $strukturTenors;
            }

            if ($returnSingle) {
                return $item;
            }

            $build[] = $item;
        }

        return $data;
    }

    private function buildStrukturTenors($links, $specificTenor = null,$plafond,$angsuran_type)
    {
        $struktur = [];
        foreach ($links as $link) {
            $struktur[] = [
                'fee_name' => $link['fee_name'],
                '3_month' => isset($link['3_month']) ? $link['3_month'] : 0,
                '6_month' => $link['6_month'],
                '12_month' => $link['12_month'],
                '18_month' => $link['18_month'],
                '24_month' => $link['24_month'],
            ];
        }

        $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

        $strukturTenors = [];

        foreach ($tenors as $tenor) {

            $tenorData = ['tenor' =>strval($tenor)];
            $total = 0;
            if($tenor == '3' && $angsuran_type == 'musiman' ){
                $tenor_name = '6_month';
            } else{
                $tenor_name = $tenor . '_month';
            }
            

            foreach ($struktur as $s) {
                $feeName = $s['fee_name'];
                $feeValue = (float) $s[$tenor_name];
                $tenorData[$feeName] = $feeValue;

                if ($feeName !== 'eff_rate') {
                    $total += $feeValue;
                }
            }

            if($angsuran_type == 'bulanan'){
                $set_tenor = $tenor;
            }else{
                switch ($tenor) {
                    case '3':
                        $set_tenor = 3;
                       break;
                    case '6':
                         $set_tenor = 3;
                        break;
                    case '12':
                         $set_tenor = 6;
                        break;
                    case '18':
                         $set_tenor = 12;
                        break;
                    case '24':
                         $set_tenor = 18;
                        break;
                }
            }

            $eff_rate = $tenorData['eff_rate'];
            $flat_rate = round(self::calculate_flat_interest($set_tenor,$eff_rate,$angsuran_type),2);
            $pokok_pembayaran = ($plafond+$total);
            $interest_margin = (int) (($flat_rate / 12) * $set_tenor * $pokok_pembayaran / 100);

            if($angsuran_type == 'bulanan'){
                if (!in_array($set_tenor, ['6', '12', '18', '24'])) {
                    $angsuran_calc = 0;
                } else {
                    $angsuran_calc = ($pokok_pembayaran + $interest_margin) / $set_tenor;
                }
            }else{
                if ($set_tenor == 3 || $set_tenor == 6) {
                    $angsuran_calc = ($pokok_pembayaran + $interest_margin);
                } elseif ($set_tenor == 12) {
                    $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 2;
                }elseif($set_tenor == 18){
                    $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 3;
                }
            }

            $tenorData['angsuran'] = ceil(round($angsuran_calc,3)/1000)*1000;
            $tenorData['total'] = $total;
            $strukturTenors["tenor_$tenor"] = $tenorData;
        }

        return $strukturTenors;
    } 
    
    function calculate_flat_interest($tenor, $eff_rate,$angsuran_type) {

        $eff_rate_decimal = $eff_rate / 100;
        $monthly_eff_rate = $eff_rate_decimal / 12;

        if ($angsuran_type === 'bulanan') {
            $compounded_factor = pow(1 + $monthly_eff_rate, -$tenor);
            $numerator = $tenor * $monthly_eff_rate;
            $denominator = 1 - $compounded_factor;
            if($denominator != 0){
                return (($numerator / $denominator) - 1) * (12 / $tenor) * 100;
            }else{
                return 0;
            }
        } else {
            if ($tenor == 3 || $tenor == 6) {
                return $eff_rate;
            } elseif ($tenor == 12 || $tenor == 18) {
                $compounded_factor = pow(1 + $monthly_eff_rate, -$tenor);
            if($tenor != 0){
                return ((($tenor * $monthly_eff_rate) / (1 - $compounded_factor) - 1) * (12 / $tenor) + 0.1) * 100;
            }else{
                return 0;
            }
            }
        }
    }    
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Taksasi;
use App\Models\M_Taksasi;
use App\Models\M_TaksasiPrice;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaksasiController extends Controller
{

    private $timeNow;

    public function __construct()
    {
        $this->timeNow = Carbon::now();
    }

    public function index(Request $request)
    {
        try {
            $data = M_Taksasi::lazy()->skip((1 - 1) * 20)->take(20);
            $dto = R_Taksasi::collection($data);

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $data = M_Taksasi::where('id',$id)->first();

            if(!$data){
                throw new Exception("Data Not Found", 1);
            }

            $dto = new R_Taksasi($data);

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $data_taksasi =[
                'brand'=> $request->brand,
                'code'=> $request->code,
                'model'=> $request->model,
                'descr'=> $request->descr,
                'create_by'=>$request->user()->id,
                'create_at'=>$this->timeNow
            ];

            $taksasi_id = M_Taksasi::create($data_taksasi);

            if(isset($request->price) && is_array($request->price)){
                foreach ($request->price as $res) {
                    $taksasi_price =[
                        'taksasi_id'=> $taksasi_id->id,
                        'year'=> $res['name'],
                        'price'=> $res['harga']
                    ];
        
                    M_TaksasiPrice::create($taksasi_price);
                }
            }
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'created successfully'], 200);
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

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $taksasi = M_Taksasi::where('id',$id)->first();

            if(!$taksasi){
                throw new Exception("Data Not Found", 1);
            }

            $data_taksasi =[
                'brand'=> $request->brand,
                'code'=> $request->code,
                'model'=> $request->model,
                'descr'=> $request->descr,
                'updated_by'=>$request->user()->id,
                'updated_at'=>$this->timeNow
            ];
           
            $taksasi->update($data_taksasi);

            $taksasi_price = M_TaksasiPrice::where('taksasi_id',$id)->delete();
        
            if(isset($request->price) && is_array($request->price)){
                foreach ($request->price as $res) {
                    $taksasi_price =[
                        'taksasi_id'=> $id,
                        'year'=> $res['name'],
                        'price'=> $res['harga']
                    ];
        
                    M_TaksasiPrice::create($taksasi_price);
                }
            }

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $req,$id)
    { 
        DB::beginTransaction();
        try {
            
            $taksasi = M_Taksasi::where('id',$id)->first();

            if(!$taksasi){
                throw new Exception("Data Not Found", 1);
            }

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $taksasi->update($update);

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found'], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

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
            $data = M_Taksasi::all();
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

    // public function update(Request $request,$id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $request->validate([
    //             'CODE' => 'unique:branch,code,'.$id,
    //             'NAME' => 'unique:branch,name,'.$id,
    //             'ADDRESS' => 'required|string',
    //             'ZIP_CODE' => 'numeric'
    //         ]);

    //         $branch = M_Branch::findOrFail($id);

    //         $request['MOD_USER'] = $request->user()->id;
    //         $request['MOD_DATE'] = Carbon::now()->format('Y-m-d');

    //         $data = array_change_key_case($request->all(), CASE_UPPER);

    //         compareData(M_Branch::class,$id,$data,$request);

    //         $branch->update($data);

    //         DB::commit();
    //         ActivityLogger::logActivity($request,"Success",200);
    //         return response()->json(['message' => 'Cabang updated successfully', "status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,'Data Not Found',404);
    //         return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    // public function destroy(Request $req,$id)
    // { 
    //     DB::beginTransaction();
    //     try {
            
    //         $users = M_Branch::findOrFail($id);

    //         $update = [
    //             'deleted_by' => $req->user()->id,
    //             'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
    //         ];

    //         $data = array_change_key_case($update, CASE_UPPER);

    //         $users->update($data);

    //         DB::commit();
    //         ActivityLogger::logActivity($req,"Success",200);
    //         return response()->json(['message' => 'Users deleted successfully', "status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req,'Data Not Found',404);
    //         return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }
}

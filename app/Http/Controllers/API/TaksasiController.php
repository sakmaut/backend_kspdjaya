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

    public function brandList(Request $request)
    {
        try {
            $data = M_Taksasi::distinct()
                    ->select('brand')
                    ->get()
                    ->pluck('brand')
                    ->toArray();

            $result = ['brand' => $data];

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function codeModelList(Request $request)
    {
        try {
            $request->validate([
                'merk' => 'required',
            ], [
                'merk.required' => 'Merk Tidak Boleh Kosong',
            ]);

            $data = M_Taksasi::select('id', 'code', DB::raw("CONCAT(model, ' - ', descr) AS model"))
                        ->where('brand', $request->merk)
                        ->distinct()
                        ->get()
                        ->toArray();


            // $year = M_TaksasiPrice::distinct()
            //         ->select('year')
            //         ->orderBy('year','asc')
            //         ->get()
            //         ->pluck('year')
            //         ->toArray();

            // foreach ($data as &$item) {
            //     $item['tahun'] = $year;
            // }
            

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function year(Request $request)
    {
        try {
            $request->validate([
                'merk' => 'required',
                'tipe' => 'required',
            ], [
                'merk.required' => 'Merk Tidak Boleh Kosong',
                'tipe.required' => 'Tipe Tidak Boleh Kosong',
            ]);

            $tipe_array = explode(' - ', $request->tipe);

            $data = M_Taksasi::distinct()
                    ->select('id')
                    ->where('brand', '=', $request->merk)
                    ->where('code', '=', $tipe_array[0])
                    ->where('model', '=', $tipe_array[1])
                    ->get();
           

            $year = M_TaksasiPrice::distinct()
                    ->select('year')
                    ->where('taksasi_id', '=',$data->first()->id)
                    ->orderBy('year','asc')
                    ->get()
                    ->toArray();

            $years = array_column($year, 'year');
            

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($years, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function price(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required',
                'tahun' => 'required',
            ], [
                'code.required' => 'Code Tidak Boleh Kosong',
                'tahun.required' => 'Tahun Tidak Boleh Kosong'
            ]);

            $tipe_array = explode(' - ', $request->code);

            $data = M_Taksasi::select('taksasi.code', 'taksasi_price.year', 
                            DB::raw('CAST(taksasi_price.price AS UNSIGNED) AS price'))
                    ->join('taksasi_price', 'taksasi_price.taksasi_id', '=', 'taksasi.id')
                    ->where('taksasi.code', '=', $tipe_array[0])
                    ->where('taksasi.model', '=', $tipe_array[1])
                    ->where('taksasi_price.year', '=',  $request->tahun)
                    ->get();

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($data, 200);
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
                'brand'=> strtoupper($request->brand),
                'code'=> strtoupper($request->code),
                'model'=> strtoupper($request->model),
                'descr'=> strtoupper($request->descr),
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

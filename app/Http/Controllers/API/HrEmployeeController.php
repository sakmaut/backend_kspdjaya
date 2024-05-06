<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_HrEmployee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrEmployeeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data =  M_HrEmployee::all();

            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $check = M_HrEmployee::where('ID',$id)->firstOrFail();

            // ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $check], 200);
        } catch (ModelNotFoundException $e) {
            // ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            // ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            // $request->validate([
            //     'code' => 'required|string|unique:branch',
            //     'name' => 'required|string|unique:branch',
            //     'address' => 'required|string'
            // ]);

            $request['CREATED_AT'] = Carbon::now()->format('Y-m-d');
            $request['CREATED_BY'] = $request->user()->id;

            $data = $request->all();
            $data = array_change_key_case($data, CASE_UPPER);

            M_HrEmployee::create($data);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Karyawan created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

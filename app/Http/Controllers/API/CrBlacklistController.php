<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrBlacklist;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrBlacklistController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data =  M_CrBlacklist::all();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function check(Request $req)
    {
        try {
            $param = $req->param;
            $blacklist = M_CrBlacklist::where('LOAN_NUMBER', $param)
                    ->orWhere('KTP', $param)
                    ->orWhere('KK', $param)
                    ->selectRaw("LOAN_NUMBER,KTP,KK, (SELECT GROUP_CONCAT(NOTE) FROM cr_blacklist WHERE LOAN_NUMBER = ? OR KTP = ? OR KK = ?) as notes", [$param, $param, $param])
                    ->first();

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json($blacklist, 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {   

            $arrayData = [  
                'LOAN_NUMBER' => $request->loan_number??null,
                'KTP' => $request->ktp??null,
                'KK' => $request->kk??null,
                'NOTE' => $request->note??null
            ];

            M_CrBlacklist::create($arrayData);

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Data Created successfully'], 200);
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
}

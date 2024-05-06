<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_LogSendOut;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Ramsey\Uuid\Uuid;

class LogSendOutController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_LogSendOut::all();
        
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function filter(Request $request)
    {
        try {
            $data = M_LogSendOut::whereNull('status')->get();
        
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function _validate($request)
    {
        $validator = $request->validate([
            'phone_number' => 'required|numeric',
            'message' => 'required|string'
        ]);

        return $validator;
    }


    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            self::_validate($request);
    
            $data_array = [
                'id' => Uuid::uuid4()->toString(),
                'phone_number' => $request->phone_number,
                'message' => $request->message,
                'status' => $request->status
            ];
        
            M_LogSendOut::create($data_array);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Sendout created successfully',"status" => 200], 200);
        } catch (QueryException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
    
            $getSendOut = M_LogSendOut::findOrFail($id);
            
            $data_array = [
                'status' => $request->status
            ];
        
            $getSendOut->update($data_array);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Sendout updated successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,'Not Found Data',404);
            return response()->json(['message' => 'Not Found Data',"status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_HrPosition;
use App\Models\M_Branch;
use App\Models\M_HrPosition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPositionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_HrPosition::all();
            $dto = R_HrPosition::collection($data);

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $check = M_HrPosition::where('ID',$id)->firstOrFail();
            $dto = new R_HrPosition($check);

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json($dto, 200);
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
            $this->validate($request, [
                'name' => 'required|string'
            ],[
                'name.required' => 'Nama Wajib Diisi'
            ]);
            
            $checkName = M_HrPosition::where('POSITION_NAME',$request->name)->first();

            if ($checkName) {
                $this->logActivity($request, 'Nama Jabatan Sudah Ada', 409);
                return response()->json(['message' => 'Nama Jabatan Sudah Ada', 'status' => 409], 409);
            }
        
            $data['POSITION_NAME'] = strtoupper($request->name);
            $data['CREATED_BY'] = $request->user()->id;
    
            M_HrPosition::create($data);
    
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

    private function logActivity(Request $request, string $message, int $statusCode)
    {
        ActivityLogger::logActivity($request,$message,$statusCode);
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $this->validate($request, [
                'name' => 'required|string'
            ],[
                'name.required' => 'Nama Wajib Diisi'
            ]);

            $existingPosition = M_HrPosition::where('POSITION_NAME', strtoupper($request->name))
                                              ->where('id', '!=', $id)
                                              ->first();

            if ($existingPosition) {
                 return response()->json(['message' => 'Nama Jabatan Sudah Ada', "status" => 409], 409);
            }

            $position = M_HrPosition::findOrFail($id);

            $data['POSITION_NAME'] = strtoupper($request->name);
            $data['CREATED_BY'] = $request->user()->id;

            $position->update($data);

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroy(Request $req,$id)
    { 
        DB::beginTransaction();
        try {
            
            $users = M_Branch::findOrFail($id);

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $data = array_change_key_case($update, CASE_UPPER);

            $users->update($data);

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'Users deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    function generateBranchCodeNumber()
    {
        $lastCode = DB::table('branch')
                    ->orderBy('CODE_NUMBER', 'desc')
                    ->first();

        if ($lastCode) {
            $lastCodeNumber = (int) substr($lastCode->CODE_NUMBER, 1);
            $newCodeNumber = $lastCodeNumber + 1;
            $newCode = str_pad($newCodeNumber, 3, '0', STR_PAD_LEFT);
        } else {
            $newCode = '001';
        }

        return $newCode;
    }
}

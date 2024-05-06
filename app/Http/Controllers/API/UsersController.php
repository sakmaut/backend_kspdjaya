<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index(Request $req)
    {
        try {
            $data = User::where('status','Active')->get();

            // ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            // ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $check = User::where('id',$id)->firstOrFail();

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
            $request->validate([
                'username' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required|string',
                'employee_id' => 'required|string'
            ]);

            $data_array = [
                'username' => $request->username,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'status' => $request->status,
                'created_by' => $request->user()->id
            ];
        
            User::create($data_array);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User created successfully',"status" => 200], 200);
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

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'email' => 'unique:users',
            ]);

            $users = User::findOrFail($id);

            $req['updated_by'] = $request->user()->id;
            $req['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');

            $users->update($request->all());
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User updated successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroy(Request $req,$id)
    { 
        DB::beginTransaction();
        try {
            
            $users = User::findOrFail($id);

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $users->update($update);

            DB::commit();
            // ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'Users deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

}

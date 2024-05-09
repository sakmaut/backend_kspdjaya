<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_HrEmployee;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class UsersController extends Controller
{
    public function index(Request $req)
    {
        try {
            $data = User::where('status','Active')->get();

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
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

    public function _validate($request){

        $validation = $request->validate([
            'username' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string',
            'employee_id' => 'required|string'
        ]);

        return $validation;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            self::_validate($request);

            M_HrEmployee::findOrFail($request->employee_id);

            $data_array = [
                'username' => $request->username,
                'employee_id' => $request->employee_id,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'status' => $request->status,
                'created_by' => $request->user()->id
            ];
        
            User::create($data_array);
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User created successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Hr Employee Id Not Found', "status" => 404], 404);
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
            $request->validate([
                'email' => 'unique:users',
            ]);

            $users = User::findOrFail($id);

            $req['updated_by'] = $request->user()->id;
            $req['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');

            $users->update($request->all());
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User updated successfully', "status" => 200], 200);
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

    // public function uploadImage(Request $req)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $this->validate($req, [
    //             'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
    //             'type' => 'required|string',
    //             'cr_prospect_id' => 'required|string'
    //         ]);

    //         $image_path = $req->file('image')->store('Cr_Prospect');

    //         $data_array_attachment = [
    //             'id' => Uuid::uuid4()->toString(),
    //             'cr_prospect_id' => $req->cr_prospect_id,
    //             'type' => $req->type,
    //             'attachment_path' => $image_path ?? ''
    //         ];

    //         M_CrProspectAttachment::create($data_array_attachment);

    //         DB::commit();
    //         ActivityLogger::logActivity($req, "Success", 200);
    //         return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => URL::to('/') . '/storage/' . $image_path], 200);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

}

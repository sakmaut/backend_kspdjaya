<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_DetailProfile;
use App\Models\M_HrEmployee;
use App\Models\M_HrEmployeeDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class DetailProfileController extends Controller
{
    public function index(Request $request,User $user)
    {
        try {

            $getEmployeID = $request->user()->employee_id;

            $employee = M_HrEmployee::where('ID', $getEmployeID)->first();

            if (!$employee || strtolower($employee->STATUS_MST) !== 'active') {
                $user->tokens()->delete();
                return response()->json(['message' => 'Profile Not Found', 'status' => 404], 404);
            }

            $dto = new R_DetailProfile($employee);

            return response()->json(['message' => 'OK', "status" => 200, 'response' => $dto], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function uploadImage(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->validate($request, [
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
                'type' => 'required|string'
            ]);

            $image_path = $request->file('image')->store('public/Employee_Image');
            $image_path = str_replace('public/', '', $image_path);

            $url = URL::to('/') . '/storage/' . $image_path;

            $data_array_attachment = [
                'ID' => Uuid::uuid4()->toString(),
                'EMPLOYEE_ID' => $request->user()->employee_id,
                'TYPE' => $request->type,
                'PATH' => $url ?? ''
            ];

            M_HrEmployeeDocument::create($data_array_attachment);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Image upload successfully', "status" => 200], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

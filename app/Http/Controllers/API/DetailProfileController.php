<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_DetailProfile;
use App\Models\M_HrEmployee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class DetailProfileController extends Controller
{
    public function index(Request $request)
    {
        try {

            $getEmployeID = $request->user()->employee_id;

            $data = M_HrEmployee::where('ID', $getEmployeID)->where('STATUS_MST', 'Active')->get();
            $dto = R_DetailProfile::collection($data);

            if (!$data) {
                return response()->json(['message' => 'Detail profile not found',"status" => 404], 404);
            }

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
            ]);

            $image = User::findOrFail($request->user()->id);

            $image_path = $request->file('image')->store('public/Employee_Image');
            $image_path = str_replace('public/', '', $image_path);

            $url = URL::to('/') . '/storage/' . $image_path;

            $data_array_attachment = [
                'profile_photo_path' => $url,
                'updated_by' => $request->user()->id,
                'updated_at' => Carbon::now()
            ];

            $image->update($data_array_attachment);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'User Id Not Found', 404);
            return response()->json(['message' => 'User Id Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

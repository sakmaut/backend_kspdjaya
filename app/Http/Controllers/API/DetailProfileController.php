<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_DetailProfile;
use App\Models\M_HrEmployee;
use Illuminate\Http\Request;

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
}

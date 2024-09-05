<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Bpkb;
use App\Models\M_CrSurvey;
use Illuminate\Http\Request;

class BpkbController extends Controller
{
    public function index(Request $request)
    {
        try {

            $branch = $request->user()->branch_id;

            $data = M_CrSurvey::leftJoin('cr_guarante_vehicle as t2', 't2.CR_SURVEY_ID', '=', 'cr_survey.id')
                                ->select('cr_survey.branch_id', 't2.*')
                                ->where('cr_survey.branch_id',$branch)
                                ->get();

            $dto = R_Bpkb::collection($data);

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

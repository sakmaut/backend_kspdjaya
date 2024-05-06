<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrProspect;
use App\Models\M_LogTemporaryLink;
use App\Models\M_SlikApproval;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;


class SlikApprovalController extends Controller
{
    public function index(Request $request,$cr_prospect_id)
    {
        try {
            $check = M_SlikApproval::where('CR_PROSPECT_ID',$cr_prospect_id)->get();

            if($check->isEmpty()){
                return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
            }

            return response()->json(['message' => 'OK',"status" => 200,'response' => $check], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function creaturl(Request $request)
    {       
        DB::beginTransaction();
        try {
            
            $cr_prospect_id = $request->id;

            $request->validate([
                'id' => 'required|string'
            ]);

            M_CrProspect::where('id',$cr_prospect_id)->firstOrFail();

            $exp_set = now()->addMinutes(10);
            $url = URL::temporarySignedRoute('approve_slik', $exp_set, ['id' => base64_encode($cr_prospect_id)]);
            $uuid =Uuid::uuid4()->toString();

            $data_array =  [
                'id' => $uuid,
                'description' => $url,
                'created_by' => 'user'
            ];

            M_LogTemporaryLink::create($data_array);

            DB::commit();
            return response()->json(['message' => 'OK',"status" => 200, 'response' => ['id' => $uuid,'url' => $url]], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function _validate($request)
    {
        $validator = $request->validate([
            'cr_prospect_id' => 'required|string'
        ]);

        return $validator;
    }

    public function timeNow(){
        $formattedDateTime = Carbon::now()->format('Y-m-d H:i:s');
        
        return $formattedDateTime;
    }

    public function approveSpv(Request $request)
    {
        DB::beginTransaction();
        try {
            self::_validate($request);

            $check = M_SlikApproval::where('CR_PROSPECT_ID',$request->cr_prospect_id)->firstOrFail();

            $request->validate([
                'spv_approval' => 'required|string'
            ]);

            $checkApprvalDeb = M_SlikApproval::where('CR_PROSPECT_ID',$request->cr_prospect_id)
                                ->where(function($query) {
                                    $query->whereNull('DEB_APPRVL')
                                        ->orWhere('DEB_APPRVL', '');
                                })
                                ->get();

            $data_array =  [
                'ONCHARGE_APPRVL' => $request->spv_approval,
                'ONCHARGE_PERSON' => $request->spv_employee_id,
                'ONCHARGE_DESCR' => $request->spv_description,
                'ONCHARGE_TIME' => self::timeNow(),
                'SLIK_RESULT' => !$checkApprvalDeb->isEmpty() ? '1:waiting approval deb' : '2:waiting slik'
            ];

            $check->update($data_array);

    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Slik Approval Updated successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function approveCustomer(Request $request)
    {
        DB::beginTransaction();
        try {
            self::_validate($request);

            $check = M_SlikApproval::where('CR_PROSPECT_ID',$request->cr_prospect_id)->firstOrFail();

            $request->validate([
                'deb_approval' => 'required|string'
            ]);

            $checkApprvalSPV = M_SlikApproval::where('CR_PROSPECT_ID',$request->cr_prospect_id)
                                ->where(function($query) {
                                    $query->whereNull('ONCHARGE_APPRVL')
                                        ->orWhere('ONCHARGE_APPRVL', '');
                                })
                                ->get();

            $data_array =  [
                'DEB_APPRVL'=> $request->deb_approval,
                'DEB_DESCR' => $request->deb_description,
                'DEB_TIME' => self::timeNow(),
                'SLIK_RESULT' => !$checkApprvalSPV->isEmpty() ? '1:waiting approval deb' : '2:waiting slik'
            ];
            
            $check->update($data_array);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Slik Approval Updated successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

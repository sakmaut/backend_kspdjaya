<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CrAppilcationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = "";
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $uuid = Uuid::uuid4()->toString();

            self::insert_cr_application($request,$uuid);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200); 
            return response()->json(['message' => 'Cabang created successfully',"status" => 200], 200);
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

    private function insert_cr_application($request,$uuid){
        $data_cr_application =[
            'ID' => $uuid,
            'CR_PROSPECT_ID' => "",
            'CLEAR_FLAG'  => "",
            'APPLICATION_NUMBER'  => "",
            'CUST_CODE' => "",
            'ACCOUNT_NUMBER'  => "",
            'SUBMISSION_FLAG'  => "",
            'SUBMISSION_VALUE'  => floatval(""),
            'PERIOD' => floatval(""),
            'CREDIT_TYPE' => "",
            'INTENDED_FOR' => "",
            'TERM_OF_PAYMENT' => "",
            'INSTALLMENT_TYPE' => "",
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        // M_CrApplication::create($data_cr_application);
    }

    private function insert_cr_personal($request){
        $data_cr_application =[  
            'ID' => "",
            'CR_APPLICATION_ID' => "",
            'PERSONAL_STATUS' => "",
            'BPR_RELATED_FLAG' => "",
            'NAME' => "",
            'GENDER' => "",
            'BIRTHPLACE' => "",
            'BIRTHDATE' => "",
            'EDUCATION' => "",
            'ID_NUMBER' => "",
            'ID_ISSUE_DATE' => "",
            'ID_VALID_DATE' => "",
            'RELATIONSHIP' => "",
            'RELIGION' => "",
            'AMENABILITY' => "",
            'ADDRESS' => "",
            'PROPERTY_STATUS' => "",
            'CITY' => "",
            'POSTAL_CODE' => "",
            'STAY_PERIOD' => "",
            'PHONE' => "",
            'PERSONAL_NUMBER' => "",
            'MOTHER' => "",
            'TIN_NUMBER' => "",
            'VERSION' => "",
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        //  M_CrPersonal::create($data_cr_application);
    }

    // private function insert_cr_business($request){
    //     $data_cr_application =[
    //         'ID' => "",
    //         'CR_APPLICATION_ID' => "",
    //         'BUSINESS_STATUS' => "",
    //         'COMPANY_NAME' => "",
    //         'COMPANY SECTION' => "",
    //         'BUSINESS_PERIOD' => "",
    //         'POSITION' => "",
    //         'ADDRESS' => "",
    //         'OFFICE_NUMBER_1' => "",
    //         'OFFICE_NUMBER_2' => "",
    //         'MONTHLY_NET_INCOME' => "",
    //         'SIDE_JOB' => "",
    //         'MONTHLY_SIDE_INCOME' => "",
    //         'VERSION' => "",
    //         'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
    //         'CREATE_USER' => $request->user()->id,
    //     ];

    //     M_CrBusiness::create($data_cr_application);
    // }

    // private function insert_cr_spouse($request){
    //     $data_cr_application =[
    //         'ID' => "",
    //         'CR_APPLICATION_ID' => "",
    //         'NAME' => "",
    //         'GENDER' => "",
    //         'BIRTHPLACE' => "",
    //         'BIRTHDATE' => "",
    //         'EDUCATION' => "",
    //         'ID_NUMBER' => "",
    //         'ID_ISSUE_DATE' => "",
    //         'ID_VALID_DATE' => "",
    //         'OCCUPATION' => "",
    //         'COMPANY_NAME' => "",
    //         'COMPANY_SECTION' => "",
    //         'BUSINESS_PERIOD' => "",
    //         'POSITION' => "",
    //         'OFFICE_NUMBER_1' => "",
    //         'OFFICE_NUMBER_2' => "",
    //         'MONTHLY_NET_INCOME' => "",
    //         'VERSION' => "",
    //         'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
    //         'CREATE_USER' => $request->user()->id,
    //     ];

    //     M_CrSpouse::create($data_cr_application);
    // }

    // private function insert_cr_guarantor($request){
    //     $data_cr_application =[
    //         'ID' => "",
    //         'CR_APPLICATION_ID' => "",
    //         'HEADER_ID' => "",
    //         'NAME' => "",
    //         'BIRTHPLACE' => "",
    //         'BIRTHDATE' => "",
    //         'ID_NUMBER' => "",
    //         'ADDRESS' => "",
    //         'CITY' => "",
    //         'POSTAL_CODE' => "",
    //         'STAY_PERIOD' => "",
    //         'PHONE' => "",
    //         'PERSONAL_NUMBER' => "",
    //         'RELATION' => "",
    //         'OCCUPATION' => "",
    //         'MONTHLY_NET_INCOME' => "",
    //         'VERSION' => "",
    //         'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
    //         'CREATE_USER' => $request->user()->id,
    //     ];

    //     M_CrGuarantor::create($data_cr_application);
    // }

    // private function insert_cr_info($request){
    //     $data_cr_application =[
    //         'ID' => "",
    //         'CR_APPLICATION_ID' => "",
    //         'PROP_TAX_NAME' => "",
    //         'ELECTRICITY_NAME' => "",
    //         'WATTAGE' => "",
    //         'PHONE_NAME' => "",
    //         'VERSION' => "",
    //         'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
    //         'CREATE_USER' => $request->user()->id,
    //     ];

    //     M_CrInfo::create($data_cr_application);
    // }

    // private function insert_cr_referral($request){
    //     $data_cr_application =[
    //         'ID' => "",
    //         'CR_APPLICATION_ID' => "",
    //         'NAME' => "",
    //         'ADDRESS' => "",
    //         'CITY' => "",
    //         'POSTAL_CODE' => "",
    //         'STAY_PERIOD' => "",
    //         'PHONE' => "",
    //         'PERSONAL_NUMBER' => "",
    //         'RELATIONSHIP' => "",
    //         'VERSION' => "",
    //         'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
    //         'CREATE_USER' => $request->user()->id,
    //     ];

    //     M_CrReferral::create($data_cr_application);
    // }
}

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
            'ID' => '',
            'BRANCH' => '',
            'FORM_NUMBER' => '',
            'ORDER_NUMBER' => '',
            'CUST_CODE' => '',
            'ENTRY_DATE' => '',
            'SUBMISSION_VALUE' => '',
            'CREDIT_TYPE' => '',
            'INSTALLMENT_COUNT' => '',
            'PERIOD' => '',
            'INSTALLMENT' => '',
            'RATE' => '',
            'VERSION' => '',
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        // M_CrApplication::create($data_cr_application);
    }

    private function insert_cr_personal($request){
        $data_cr_application =[  
            'ID' => '',
            'CUST_CODE' => '',
            'NAME' => '',
            'ALIAS' => '',
            'GENDER' => '',
            'BIRTHPLACE' => '',
            'BIRTHDATE' => '',
            'MARTIAL_STATUS' => '',
            'MARTIAL_DATE' => '',
            'ID_TYPE' => '',
            'ID_NUMBER' => '',
            'ID_ISSUE_DATE' => '',
            'ID_VALID_DATE' => '',
            'ADDRESS' => '',
            'RT' => '',
            'RW' => '',
            'PROVINCE' => '',
            'CITY' => '',
            'KELURAHAN' => '',
            'KECAMATAN' => '',
            'ZIP_CODE' => '',
            'KK' => '',
            'CITIZEN' => '',
            'INS_ADDRESS' => '',
            'INS_RT' => '',
            'INS_RW' => '',
            'INS_PROVINCE' => '',
            'INS_CITY' => '',
            'INS_KELURAHAN' => '',
            'INS_KECAMATAN' => '',
            'INS_ZIP_CODE' => '',
            'OCCUPATION' => '',
            'OCCUPATION_ON_ID' => '',
            'RELIGION' => '',
            'EDUCATION' => '',
            'PROPERTY_STATUS' => '',
            'PHONE_HOUSE' => '',
            'PHONE_PERSONAL' => '',
            'PHONE_OFFICE' => '',
            'EXT_1' => '',
            'EXT_2' => '',
            'VERSION' => '',
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        //  M_CrPersonal::create($data_cr_application);
    }

    private function insert_cr_personal_extra($request){
        $data_cr_application =[  
            'ID' => '',
            'APPLICATION_ID' => '',
            'BI_NAME' => '',
            'EMAIL' => '',
            'INFO' => '',
            'OTHER_OCCUPATION_1' => '',
            'OTHER_OCCUPATION_2' => '',
            'OTHER_OCCUPATION_3' => '',
            'OTHER_OCCUPATION_4' => '',
            'MAIL_ADDRESS' => '',
            'MAIL_RT' => '',
            'MAIL_RW' => '',
            'MAIL_PROVINCE' => '',
            'MAIL_CITY' => '',
            'MAIL_KELURAHAN' => '',
            'MAIL_KECAMATAN' => '',
            'MAIL_ZIP_CODE' => '',
            'EMERGENCY_NAME' => '',
            'EMERGENCY_ADDRESS' => '',
            'EMERGENCY_RT' => '',
            'EMERGENCY_RW' => '',
            'EMERGENCY_PROVINCE' => '',
            'EMERGENCY_CITY' => '',
            'EMERGENCY_KELURAHAN' => '',
            'EMERGENCY_KECAMATAN' => '',
            'EMERGENCY_ZIP_CODE' => '',
            'EMERGENCY_PHONE_HOUSE' => '',
            'EMERGENCY_PHONE_PERSONAL'  => '' 
        ];

        //  M_CrPersonal::create($data_cr_application);
    }

    private function insert_bank_account($array_data,$request){
        foreach ($array_data as $result) {
            $data_cr_application =[  
                'ID' => '',
                'APPLICATION_ID' => '',
                'BANK_CODE' => '',
                'BANK_NAME' => '',
                'ACCOUNT_NUMBER' => '',
                'ACCOUNT_NAME' => '',
                'PREFERENCE_FLAG' => '',
                'STATUS'    
            ];
        }
    }

   
}

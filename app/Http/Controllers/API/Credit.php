<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationGuarantor;
use App\Models\M_CrCollateral;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrSurvey;
use App\Models\M_Customer;
use App\Models\M_CustomerExtra;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class Credit extends Controller
{

    private $timeNow;

    public function __construct()
    {
        $this->timeNow = Carbon::now();
    }

    public function index(Request $request)
    {
        try {
            $check = M_CrApplication::where('ORDER_NUMBER',$request->order_number)->first();

            if (!$check) {
                throw new Exception("Order Number Is Not Exist", 404);
            }
    
            return response()->json(self::buildData($request,$check), 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    function queryKapos($branchID){
        $result = DB::table('users')
                    ->select('fullname', 'position', 'branch.address','branch.name','branch.city')
                    ->join('branch', 'branch.id', '=', 'users.branch_id')
                    ->where('branch.id', '=', $branchID)
                    ->where('users.position', '=', 'KAPOS')
                    ->first();

        return $result;
    }

    private function buildData($request,$data){
        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_guarante_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->first();
        $cr_guarantor = M_CrApplicationGuarantor::where('APPLICATION_ID',$data->ID)->first();
        $pihak1= self::queryKapos($data->BRANCH);
        $loan_number = generateCode($request, 'credit', 'LOAN_NUMBER');

        $principal = $data->SUBMISSION_VALUE + $data->NET_ADMIN;
        // $annualInterestRate = $data->FLAT_RATE;
        $effRate = $data->EFF_RATE;
        $loanTerm = $data->PERIOD;
        $angsuran = $data->INSTALLMENT;
        $set_tgl_awal =$request->tgl_awal;

        $data_credit_schedule = generateAmortizationSchedule($principal,$angsuran, $set_tgl_awal,$effRate, $loanTerm);
        $installment_count = count($data_credit_schedule);

        $check_exist = M_Credit::where('ORDER_NUMBER', $request->order_number)->first();

        if (!$check_exist && 'yes' === $request->flag) {
            self::insert_credit($request, $data, $loan_number,$installment_count);
            foreach ($data_credit_schedule as $list) {
                $credit_schedule =
                [
                    'id' => Uuid::uuid7()->toString(),
                    'loan_number' => $loan_number,
                    'PAYMENT_DATE' => $list['tgl_angsuran'],
                    'principal' => converttodecimal($list['pokok']),
                    'interest' => converttodecimal($list['bunga']),
                    'installment' => converttodecimal($list['total_angsuran']),
                    'principal_remains' => converttodecimal($list['baki_debet']),
                    'PAID_FLAG' => ''
                ];

                M_CreditSchedule::create($credit_schedule);
            }
            self::insert_customer($request,$data);
            self::insert_customer_xtra($data);
            self::insert_collateral($request,$data);
        }

        $data = [
            "no_perjanjian" => !$check_exist? $loan_number:$check_exist->LOAN_NUMBER,
            "cabang" => 'CABANG '.strtoupper($pihak1->name)??null,
            "kota" => strtoupper($pihak1->city)??null,
            "tgl_cetak" => Carbon::now()->format('d/m/Y'),
            "flag" => !$check_exist?0:1,
             "pihak_1" => [
                "nama" => strtoupper($pihak1->fullname)??null,
                "jabatan" => strtoupper($pihak1->position)??null,
                "alamat_kantor" => strtoupper($pihak1->address)??null
             ],
             "pihak_2" => [
                "nama" =>strtoupper($cr_personal->NAME)??null,
                "no_identitas" => strtoupper($cr_personal->ID_NUMBER)??null,
                "alamat" => strtoupper($cr_personal->ADDRESS)??null
             ],
             "penjamin" => [
                "nama" => $cr_guarantor->NAME ?? null,
                "jenis_kelamin" => $cr_guarantor->GENDER?? null,
                "tempat_lahir" => $cr_guarantor->BIRTHPLACE?? null,
                "tgl_lahir" =>$cr_guarantor->BIRTHDATE?? null,
                "alamat" => $cr_guarantor->ADDRESS?? null,
                "tipe_identitas"  => $cr_guarantor->IDENTIY_TYPE?? null,
                "no_identitas"  => $cr_guarantor->NUMBER_IDENTITY?? null,
                "pekerjaan"  => $cr_guarantor->OCCUPATION?? null,
                "lama_bekerja"  => $cr_guarantor->WORK_PERIOD?? null,
                "hub_cust" => $cr_guarantor->STATUS_WITH_DEBITUR?? null,
                "no_hp" => $cr_guarantor->MOBILE_NUMBER?? null,
                "pendapatan" => $cr_guarantor->INCOME?? null,   
            ],
             "pokok_margin" =>bilangan($principal)??null,
             "tenor" => bilangan($data->PERIOD,false)??null,
             "tgl_awal_cicilan" => Carbon::parse($set_tgl_awal)->format('d/m/Y')??null,
             "tgl_akhir_cicilan" => Carbon::parse($set_tgl_awal)->addMonths($data->PERIOD)->format('d/m/Y')??null,
             "angsuran" =>bilangan($angsuran)??null,
             "opt_periode" => $data->OPT_PERIODE??null,
             "tipe_jaminan" => $data->CREDIT_TYPE??null,
             "no_bpkb" =>  $cr_guarante_vehicle->BPKB_NUMBER??null,
             "atas_nama" => $cr_guarante_vehicle->ON_BEHALF??null,
             "merk" => $cr_guarante_vehicle->BRAND??null,
             "type" => $cr_guarante_vehicle->TYPE??null,
             "tahun" => $cr_guarante_vehicle->PRODUCTION_YEAR??null,
             "warna" => $cr_guarante_vehicle->COLOR??null,
             "no_polisi" => $cr_guarante_vehicle->POLICE_NUMBER??null,
             "no_rangka" =>$cr_guarante_vehicle->CHASIS_NUMBER??null,
             "no_mesin" => $cr_guarante_vehicle->ENGINE_NUMBER??null,
             "struktur" => $data_credit_schedule??null
        ];

        return $data;
    }

    private function insert_credit($request,$data,$loan_number,$installment_count){

        $survey = M_CrSurvey::find($data->CR_SURVEY_ID);

        $data_credit =[
            'ID' => Uuid::uuid7()->toString(),
            'LOAN_NUMBER' => $loan_number,
            'STATUS_REC' => $data->BRANCH,
            'BRANCH'   => $data->BRANCH,
            'CUST_CODE' => $data->CUST_CODE,
            'ORDER_NUMBER' => $data->ORDER_NUMBER,
            'COLLECTIBILITY'  => '',
            'MCF_ID'  => $survey->created_by??null,
            'ENTRY_DATE'  => Carbon::now(),
            'FIRST_ARR_DATE'  => null,
            'INSTALLMENT_DATE'  => $request->tgl_awal??null,
            'END_DATE'  => Carbon::parse($request->tgl_awal)->addMonths($data->PERIOD)->format('Y-m-d')??null,
            'PCPL_ORI'  => $data->SUBMISSION_VALUE + ($data->NET_ADMIN ?? 0)??null,
            'PAID_PRINCIPAL'  => null,
            'PAID_INTEREST'  => null,
            'PAID_PENALTY'  => null,
            'DUE_PRINCIPAL'  => null,
            'DUE_INTEREST'  => null,
            'DUE_PENALTY'  => null,
            'CREDIT_TYPE'  =>$data->CREDIT_TYPE??null,
            'INSTALLMENT_COUNT'  => $installment_count,
            'PERIOD'  => $data->PERIOD,
            'INSTALLMENT'  => $request->angsuran,
            'FLAT_RATE'  => $data->FLAT_RATE??null,
            'EFF_RATE'  => $data->EFF_RATE??null,
            'VERSION'  => 1,
            'CREATED_BY' => $request->user()->id,
            'CREATED_AT' => Carbon::now(),
        ];

        M_Credit::create($data_credit);
    }

    private function insert_customer($request,$data){

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID',$data->ID)->first();

        $data_customer =[
            'ID' => Uuid::uuid7()->toString(),
            'CUST_CODE' =>$cr_personal->CUST_CODE,
            'NAME' =>$cr_personal->NAME,
            'ALIAS' =>$cr_personal->ALIAS,
            'GENDER' =>$cr_personal->GENDER,
            'BIRTHPLACE' =>$cr_personal->BIRTHPLACE,
            'BIRTHDATE' =>$cr_personal->BIRTHDATE,
            'BLOOD_TYPE' =>$cr_personal->BLOOD_TYPE,
            'MOTHER_NAME' => $cr_order->MOTHER_NAME,
            'NPWP' =>$cr_personal->CUST_CODE,
            'MARTIAL_STATUS' =>$cr_personal->MARTIAL_STATUS,
            'MARTIAL_DATE' =>$cr_personal->MARTIAL_DATE,
            'ID_TYPE' =>$cr_personal->ID_TYPE,
            'ID_NUMBER' =>$cr_personal->ID_NUMBER,
            'ID_ISSUE_DATE' =>$cr_personal->ID_ISSUE_DATE,
            'ID_VALID_DATE' =>$cr_personal->ID_VALID_DATE,
            'ADDRESS' =>$cr_personal->ADDRESS,
            'RT' =>$cr_personal->RT,
            'RW' =>$cr_personal->RW,
            'PROVINCE' =>$cr_personal->PROVINCE,
            'CITY' =>$cr_personal->CITY,
            'KELURAHAN' =>$cr_personal->KELURAHAN,
            'KECAMATAN'=>$cr_personal->KECAMATAN ,
            'ZIP_CODE' =>$cr_personal->ZIP_CODE,
            'KK' =>$cr_personal->KK,
            'CITIZEN' =>$cr_personal->CITIZEN,
            'INS_ADDRESS' =>$cr_personal->INS_ADDRESS,
            'INS_RT' =>$cr_personal->INS_RT,
            'INS_RW' =>$cr_personal->INS_RW,
            'INS_PROVINCE' =>$cr_personal->INS_PROVINCE,
            'INS_CITY' =>$cr_personal->INS_CITY,
            'INS_KELURAHAN' =>$cr_personal->INS_KELURAHAN,
            'INS_KECAMATAN' =>$cr_personal->INS_KECAMATAN,
            'INS_ZIP_CODE' =>$cr_personal->INS_ZIP_CODE,
            'OCCUPATION' =>$cr_personal->OCCUPATION,
            'OCCUPATION_ON_ID' =>$cr_personal->OCCUPATION_ON_ID,
            'INCOME' => $cr_order->INCOME_PERSONAL,
            'RELIGION' =>$cr_personal->RELIGION,
            'EDUCATION' =>$cr_personal->EDUCATION,
            'PROPERTY_STATUS' =>$cr_personal->PROPERTY_STATUS,
            'PHONE_HOUSE' =>$cr_personal->PHONE_HOUSE,
            'PHONE_PERSONAL' =>$cr_personal->PHONE_PERSONAL,
            'PHONE_OFFICE' =>$cr_personal->PHONE_OFFICE,
            'EXT_1' =>$cr_personal->EXT_1,
            'EXT_2' =>$cr_personal->EXT_2,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now(),
            'CREATE_USER' => $request->user()->id,
        ];

        M_Customer::create($data_customer);
    }

    private function insert_customer_xtra($data){

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID',$data->ID)->first();

        $data_customer_xtra =[
            'ID' => Uuid::uuid7()->toString(),
            'CUST_CODE' =>$cr_personal->CUST_CODE,
            'OTHER_OCCUPATION_1' =>$cr_personal_extra->OTHER_OCCUPATION_1,
            'OTHER_OCCUPATION_2' =>$cr_personal_extra->OTHER_OCCUPATION_2,
            'SPOUSE_NAME' => null,
            'SPOUSE_BIRTHPLACE' => null,
            'SPOUSE_BIRTHDATE' => null,
            'SPOUSE_ID_NUMBER' => null,
            'SPOUSE_INCOME' => null,
            'SPOUSE_ADDRESS' => null,
            'SPOUSE_RT' => null,
            'SPOUSE_RW' => null,
            'SPOUSE_PROVINCE' => null,
            'SPOUSE_CITY' => null,
            'SPOUSE_KELURAHAN' => null,
            'SPOUSE_KECAMATAN' => null,
            'SPOUSE_ZIP_CODE' => null,
            'INS_ADDRESS' =>null,
            'INS_RT' =>null,
            'INS_RW' =>null,
            'INS_PROVINCE' =>null,
            'INS_CITY' =>null,
            'INS_KELURAHAN' =>null,
            'INS_KECAMATAN' =>null,
            'INS_ZIP_CODE' =>null,
            'EMERGENCY_NAME' =>$cr_personal_extra->EMERGENCY_NAME,
            'EMERGENCY_ADDRESS' =>$cr_personal_extra->EMERGENCY_ADDRESS,
            'EMERGENCY_RT' =>$cr_personal_extra->EMERGENCY_RT,
            'EMERGENCY_RW' =>$cr_personal_extra->EMERGENCY_RW,
            'EMERGENCY_PROVINCE' =>$cr_personal_extra->EMERGENCY_PROVINCE,
            'EMERGENCYL_CITY' =>$cr_personal_extra->EMERGENCYL_CITY,
            'EMERGENCY_KELURAHAN' =>$cr_personal_extra->EMERGENCY_KELURAHAN,
            'EMERGENCYL_KECAMATAN' =>$cr_personal_extra->EMERGENCYL_KECAMATAN,
            'EMERGENCY_ZIP_CODE' =>$cr_personal_extra->EMERGENCY_ZIP_CODE,
            'EMERGENCY_PHONE_HOUSE' =>$cr_personal_extra->EMERGENCY_PHONE_HOUSE,
            'EMERGENCY_PHONE_PERSONAL' =>$cr_personal_extra->EMERGENCY_PHONE_PERSONAL
        ];

        M_CustomerExtra::create($data_customer_xtra);
    }

    private function insert_collateral($request,$data){
        $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->get();

        if($data_collateral->isNotEmpty()){
            foreach ($data_collateral as $res) {
                $data_jaminan = [
                    'HEADER_ID' => "",
                    'TYPE' => $res->TYPE??null,
                    'BRAND' => $res->BRAND??null,
                    'PRODUCTION_YEAR' => $res->PRODUCTION_YEAR??null,
                    'COLOR' => $res->COLOR??null,
                    'ON_BEHALF' => $res->ON_BEHALF??null,
                    'POLICE_NUMBER' => $res->POLICE_NUMBER??null,
                    'CHASIS_NUMBER' => $res->CHASIS_NUMBER??null,
                    'ENGINE_NUMBER' => $res->ENGINE_NUMBER??null,
                    'BPKB_NUMBER' => $res->BPKB_NUMBER??null,
                    'STNK_NUMBER' => $res->STNK_NUMBER??null,
                    'VALUE' => $res->VALUE??null,
                    'COLLATERAL_FLAG' => "",
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];
    
                M_CrCollateral::create($data_jaminan);
            }
        }
    }
}

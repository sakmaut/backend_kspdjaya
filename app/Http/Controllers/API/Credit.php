<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationGuarantor;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteSertification;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use App\Models\M_CustomerExtra;
use App\Models\M_LocationStatus;
use Carbon\Carbon;
use DateTime;
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
            $data = M_CrApplication::where('ORDER_NUMBER',$request->order_number)->first();

            if (!$data) {
                throw new Exception("Order Number Is Not Exist", 404);
            }

            return response()->json($this->buildData($request,$data), 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    function queryKapos($branchID){
        $result = DB::table('users')
                    ->select('fullname', 'position','no_ktp','alamat', 'branch.address','branch.name','branch.city')
                    ->join('branch', 'branch.id', '=', 'users.branch_id')
                    ->where('branch.id', '=', $branchID)
                    ->where('users.position', '=', 'KAPOS')
                    ->first();

        return $result;
    }

    private function buildData($request,$data){
        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_guarantor = M_CrApplicationGuarantor::where('APPLICATION_ID',$data->ID)->get();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID',$data->ID)->first();
        $pihak1= $this->queryKapos($data->BRANCH);
       
        $set_tgl_awal = $request->tgl_awal;
        $principal = $data->POKOK_PEMBAYARAN;
        $angsuran = $data->INSTALLMENT;
        $loanTerm = $data->TENOR;

        $data_credit_schedule = $this->generateAmortizationSchedule($set_tgl_awal,$data);

        $installment_count = count($data_credit_schedule);

        $schedule = [];
        $check_exist = M_Credit::where('ORDER_NUMBER', $request->order_number)->first();

        if($check_exist != null && !empty($check_exist->LOAN_NUMBER)){
            $credit_schedule = M_CreditSchedule::where('LOAN_NUMBER',$check_exist->LOAN_NUMBER)->get();

            $no = 1;
            foreach ($credit_schedule as $key) {
                $schedule[] = [
                    'angsuran_ke' =>  $no++,
                    'tgl_angsuran' => $key['PAYMENT_DATE'],
                    'pokok' => $key['PRINCIPAL'],
                    'bunga' => $key['INTEREST'],
                    'total_angsuran' => $key['INSTALLMENT'],
                    'baki_debet' => $key['PRINCIPAL_REMAINS']
                ];
            }
        }
      
        $SET_UUID = Uuid::uuid7()->toString();
        $loan_number = generateCode($request, 'credit', 'LOAN_NUMBER');
        $cust_code = generateCustCode($request, 'customer', 'CUST_CODE');
        
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get(); 

        $guarente_sertificat = M_CrGuaranteSertification::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get(); 


        if (!$check_exist && $request->flag == 'yes') {

            $this->insert_credit($SET_UUID,$request, $data, $loan_number,$installment_count, $cust_code);

            $no =1;
            foreach ($data_credit_schedule as $list) {
                $credit_schedule =
                [
                    'ID' => Uuid::uuid7()->toString(),
                    'LOAN_NUMBER' => $loan_number,
                    'INSTALLMENT_COUNT' => $no++,
                    'PAYMENT_DATE' => parseDatetoYMD($list['tgl_angsuran']),
                    'PRINCIPAL' => $list['pokok'],
                    'INTEREST' => $list['bunga'],
                    'INSTALLMENT' => $list['total_angsuran'],
                    'PRINCIPAL_REMAINS' => $list['baki_debet']
                ];

                M_CreditSchedule::create($credit_schedule);
            }
            
            $this->insert_customer($request,$data, $cust_code);
            $this->insert_customer_xtra($data, $cust_code);
            $this->insert_collateral($request,$data,$SET_UUID);
            $this->insert_collateral_sertification($request,$data,$SET_UUID);
        }

        $data = [
           "no_perjanjian" => !$check_exist && $request->flag == 'yes' ? $loan_number??null: $check_exist->LOAN_NUMBER??null,
            "cabang" => 'CABANG '.strtoupper($pihak1->name)??null,
            "kota" => strtoupper($pihak1->city)??null,
            "tgl_cetak" => !empty($check_exist)? Carbon::parse($check_exist->CREATED_AT)->format('Y-m-d') : null,
            "tgl_awal_angsuran" => !empty($check_exist)? Carbon::parse($check_exist->INSTALLMENT_DATE)->format('Y-m-d'): Carbon::parse($set_tgl_awal)->format('Y-m-d'),
            "flag" => !$check_exist?0:1,
             "pihak_1" => [
                "nama" => strtoupper($pihak1->fullname)??null,
                "jabatan" => strtoupper($pihak1->position)??null,
                "no_ktp" => strtoupper($pihak1->no_ktp)??null,
                "alamat" => strtoupper($pihak1->alamat)??null,
                "alamat_kantor" => strtoupper($pihak1->address)??null
             ],
             "pihak_2" => [
                "nama" =>strtoupper($cr_personal->NAME)??null,
                "no_identitas" => strtoupper($cr_personal->ID_NUMBER)??null,
                "alamat" => strtoupper($cr_personal->ADDRESS)??null
             ],
            "penjamin" => [],
            "pasangan" => [
                "nama_pasangan" =>$cr_spouse->NAME ?? null,
                "tmptlahir_pasangan" =>$cr_spouse->BIRTHPLACE ?? null,
                "pekerjaan_pasangan" => $cr_spouse->OCCUPATION ?? null,
                "tgllahir_pasangan" => $cr_spouse->BIRTHDATE ?? null,
                "alamat_pasangan" => $cr_spouse->ADDRESS ?? null
            ],
             "pokok_margin" =>bilangan($principal)??null,
             "tenor" => bilangan($data->TENOR,false)??null,
             "tgl_awal_pk" => !empty($check_exist)?Carbon::parse($check_exist->ENTRY_DATE)->format('Y-m-d'):parseDatetoYMD($set_tgl_awal),
             "tgl_akhir_pk" => !empty($check_exist)?Carbon::parse($check_exist->END_DATE)->format('Y-m-d'):add_months(parseDatetoYMD($set_tgl_awal),$loanTerm),
             "angsuran" =>bilangan($angsuran)??null,
             "opt_periode" => $data->OPT_PERIODE??null,
             "jaminan" => [],
             "struktur" => $check_exist != null && !empty($check_exist->LOAN_NUMBER)?$schedule:$data_credit_schedule??null
        ];

        foreach ($cr_guarantor as $list) {
            $data['penjamin'][] = [
                "id" => $list->ID ?? null,
                "nama" => $list->NAME ?? null,
                "jenis_kelamin" => $list->GENDER?? null,
                "tempat_lahir" => $list->BIRTHPLACE?? null,
                "tgl_lahir" =>$list->BIRTHDATE?? null,
                "alamat" => $list->ADDRESS?? null,
                "tipe_identitas"  => $list->IDENTIY_TYPE?? null,
                "no_identitas"  => $list->NUMBER_IDENTITY?? null,
                "pekerjaan"  => $list->OCCUPATION?? null,
                "lama_bekerja"  => intval($list->WORK_PERIOD?? null),
                "hub_cust" => $list->STATUS_WITH_DEBITUR?? null,
                "no_hp" => $list->MOBILE_NUMBER?? null,
                "pendapatan" => $list->INCOME?? null,   
            ];    
        }

        foreach ($guarente_vehicle as $list) {
            $data['jaminan'][] = [
                "type" => "kendaraan",
                'counter_id' => $list->HEADER_ID,
                "atr" => [ 
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "alamat_bpkb" => $list->BPKB_ADDRESS,
                    "no_faktur" => $list->INVOICE_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE
                ]
            ];    
        }

        foreach ($guarente_sertificat as $list) {
            $data['jaminan'][] = [
                "type" => "sertifikat",
                'counter_id' => $list->HEADER_ID,
                "atr" => [ 
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    "no_sertifikat" => $list->NO_SERTIFIKAT,
                    "status_kepemilikan" => $list->STATUS_KEPEMILIKAN,
                    "imb" => $list->IMB,
                    "luas_tanah" => $list->LUAS_TANAH,
                    "luas_bangunan" => $list->LUAS_BANGUNAN,
                    "lokasi" => $list->LOKASI,
                    "provinsi" => $list->PROVINSI,
                    "kab_kota" => $list->KAB_KOTA,
                    "kec" => $list->KECAMATAN,
                    "desa" => $list->DESA,
                    "atas_nama" => $list->ATAS_NAMA,
                    "nilai" => (int) $list->NILAI
                ]
            ];    
        }

        return $data;
    }

    private function insert_credit($SET_UUID,$request,$data,$loan_number,$installment_count, $cust_code){

        $survey = M_CrSurvey::find($data->CR_SURVEY_ID);

        $setDate = parseDatetoYMD($request->tgl_awal);

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER',$cr_personal->ID_NUMBER)->first();

        $data_credit =[
            'ID' =>  $SET_UUID,
            'LOAN_NUMBER' => $loan_number,
            'STATUS_REC' => $data->BRANCH,
            'BRANCH'   => $data->BRANCH,
            'ORDER_NUMBER' => $data->ORDER_NUMBER,
            'STATUS'  => 'A',
            'MCF_ID'  => $survey->created_by??null,
            'ENTRY_DATE'  => $setDate??null,
            'FIRST_ARR_DATE'  => null,
            'INSTALLMENT_DATE'  => $setDate??null,
            'END_DATE'  => add_months($setDate,$data->PERIOD)??null,
            'PCPL_ORI'  => $data->SUBMISSION_VALUE + ($data->TOTAL_ADMIN ?? 0)??null,
            'PAID_PRINCIPAL'  => null,
            'PAID_INTEREST'  => null,
            'PAID_PENALTY'  => null,
            'DUE_PRINCIPAL'  => null,
            'DUE_INTEREST'  => null,
            'DUE_PENALTY'  => null,
            'CREDIT_TYPE'  =>$data->CREDIT_TYPE??null,
            'INSTALLMENT_COUNT'  => $installment_count,
            'PERIOD'  => $data->PERIOD,
            'INSTALLMENT'  => $data->INSTALLMENT,
            'FLAT_RATE'  => $data->FLAT_RATE??null,
            'EFF_RATE'  => $data->EFF_RATE??null,
            'VERSION'  => 1,
            'CREATED_BY' => $request->user()->id,
            'CREATED_AT' => Carbon::now(),
        ];

         
        if(!$check_customer_ktp){
            $data_credit['CUST_CODE'] = $cust_code;
        }else{
            $data_credit['CUST_CODE'] = $check_customer_ktp->CUST_CODE;
        }

        $credit = M_Credit::create($data_credit);
        $last_id = $credit->id;

        return $last_id;
    }

    private function insert_customer($request,$data, $cust_code){

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID',$data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER',$cr_personal->ID_NUMBER)->first();

        $getAttachment = DB::select(
            "   SELECT TYPE,COUNTER_ID,PATH
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE  CR_SURVEY_ID = '$data->CR_SURVEY_ID'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        $data_customer =[
            'NAME' =>$cr_personal->NAME,
            'ALIAS' =>$cr_personal->ALIAS,
            'GENDER' =>$cr_personal->GENDER,
            'BIRTHPLACE' =>$cr_personal->BIRTHPLACE,
            'BIRTHDATE' =>$cr_personal->BIRTHDATE,
            'BLOOD_TYPE' =>$cr_personal->BLOOD_TYPE,
            'MOTHER_NAME' => $cr_order->MOTHER_NAME,
            'NPWP' =>$cr_order->NO_NPWP,
            'MARTIAL_STATUS' =>$cr_personal->MARTIAL_STATUS,
            'MARTIAL_DATE' =>$cr_personal->MARTIAL_DATE,
            'ID_TYPE' =>$cr_personal->ID_TYPE,
            'ID_NUMBER' =>$cr_personal->ID_NUMBER,
            'KK_NUMBER' =>$cr_personal->KK,
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
        
        if(!$check_customer_ktp){
            $data_customer['ID'] = Uuid::uuid7()->toString();
            $data_customer['CUST_CODE'] = $cust_code;
            $last_id = M_Customer::create($data_customer);

            $this->createCustomerDocuments($last_id->ID,$getAttachment);
        }else{
            $check_customer_ktp->update($data_customer);

            $this->createCustomerDocuments($check_customer_ktp->ID,$getAttachment);
        }
    }

    private function createCustomerDocuments($customerId, $attachments) {
        foreach ($attachments as $res) {
            $custmer_doc_data = [
                'CUSTOMER_ID' => $customerId,
                'TYPE' => $res->TYPE,
                'COUNTER_ID' => $res->COUNTER_ID,
                'PATH' => $res->PATH,
                'TIMESTAMP' => round(microtime(true) * 1000)
            ];
    
            M_CustomerDocument::create($custmer_doc_data);
        }
    }

    private function insert_customer_xtra($data, $cust_code){

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID',$data->ID)->first();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID',$data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID',$data->ID)->first();
        $update = M_CustomerExtra::where('CUST_CODE', $check_customer_ktp->CUST_CODE)->first();


        $data_customer_xtra =[
            'OTHER_OCCUPATION_1' =>$cr_personal_extra->OTHER_OCCUPATION_1??null,
            'OTHER_OCCUPATION_2' =>$cr_personal_extra->OTHER_OCCUPATION_2??null,
            'SPOUSE_NAME' =>  $cr_spouse->NAME??null,
            'SPOUSE_BIRTHPLACE' =>  $cr_spouse->BIRTHPLACE??null,
            'SPOUSE_BIRTHDATE' =>  $cr_spouse->BIRTHDATE??null,
            'SPOUSE_ID_NUMBER' => $cr_spouse->NUMBER_IDENTITY??null,
            'SPOUSE_INCOME' => $cr_order->INCOME_SPOUSE??null,
            'SPOUSE_ADDRESS' => $cr_spouse->ADDRESS??null,
            'SPOUSE_OCCUPATION' => $cr_spouse->OCCUPATION??null,
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
            'EMERGENCY_NAME' =>$cr_personal_extra->EMERGENCY_NAME??null,
            'EMERGENCY_ADDRESS' =>$cr_personal_extra->EMERGENCY_ADDRESS??null,
            'EMERGENCY_RT' =>$cr_personal_extra->EMERGENCY_RT??null,
            'EMERGENCY_RW' =>$cr_personal_extra->EMERGENCY_RW??null,
            'EMERGENCY_PROVINCE' =>$cr_personal_extra->EMERGENCY_PROVINCE??null,
            'EMERGENCYL_CITY' =>$cr_personal_extra->EMERGENCY_CITY??null,
            'EMERGENCY_KELURAHAN' =>$cr_personal_extra->EMERGENCY_KELURAHAN??null,
            'EMERGENCYL_KECAMATAN' =>$cr_personal_extra->EMERGENCY_KECAMATAN??null,
            'EMERGENCY_ZIP_CODE' =>$cr_personal_extra->EMERGENCY_ZIP_CODE??null,
            'EMERGENCY_PHONE_HOUSE' =>$cr_personal_extra->EMERGENCY_PHONE_HOUSE??null,
            'EMERGENCY_PHONE_PERSONAL' =>$cr_personal_extra->EMERGENCY_PHONE_PERSONAL??null
        ];

        if (!$update) {
            $data_customer_xtra['ID'] = Uuid::uuid7()->toString();
            $data_customer_xtra['CUST_CODE'] =  $cust_code;
            M_CustomerExtra::create($data_customer_xtra);
        } else {
            $update->update($data_customer_xtra);
        }
    }

    private function insert_collateral($request,$data,$lastID){
        $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
                                $query->whereNull('DELETED_AT')
                                    ->orWhere('DELETED_AT', '');
                            })->get(); 

        if($data_collateral->isNotEmpty()){
            foreach ($data_collateral as $res) {
                $data_jaminan = [
                    'HEADER_ID' => "",
                    'CR_CREDIT_ID' => $lastID??null,
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
                    'LOCATION_BRANCH' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];
    
                $execute = M_CrCollateral::create($data_jaminan);

                $log = [
                    'COLLATERAL_ID' => $execute->ID,
                    'TYPE' => 'kendaraan',
                    'LOCATION' => $data->BRANCH,
                    'STATUS' =>'NORMAL',
                    'CREATE_BY' => $request->user()->id,
                    'CREATED_AT' => $this->timeNow,
                    'COLLATERAL_FLAG' => $data->BRANCH,
                ];

                M_LocationStatus::create($log);
            }
        }
    }

    private function insert_collateral_sertification($request,$data,$lastID){
        $data_collateral = M_CrGuaranteSertification::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
                                $query->whereNull('DELETED_AT')
                                    ->orWhere('DELETED_AT', '');
                            })->get(); 

        if($data_collateral->isNotEmpty()){
            foreach ($data_collateral as $res) {
                $data_jaminan = [
                    'HEADER_ID' => "",
                    'CR_CREDIT_ID' => $lastID??null,
                    'STATUS_JAMINAN' => $res->STATUS_JAMINAN,
                    'NO_SERTIFIKAT' => $res->NO_SERTIFIKAT,
                    'STATUS_KEPEMILIKAN' => $res->STATUS_KEPEMILIKAN,
                    'IMB' => $res->IMB,
                    'LUAS_TANAH' => $res->LUAS_TANAH,
                    'LUAS_BANGUNAN' => $res->LUAS_BANGUNAN,
                    'LOKASI' => $res->LOKASI,
                    'PROVINSI' => $res->PROVINSI,
                    'KAB_KOTA' => $res->KAB_KOTA,
                    'KECAMATAN' => $res->KECAMATAN,
                    'DESA' => $res->DESA,
                    'ATAS_NAMA' => $res->ATAS_NAMA,
                    'NILAI' => $res->NILAI,
                    'LOCATION' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];
    
                $execute =  M_CrCollateralSertification::create($data_jaminan);

                $log = [
                    'COLLATERAL_ID' => $execute->id,
                    'TYPE' => 'sertifikat',
                    'STATUS' =>'NORMAL',
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'CREATED_BY' => $request->user()->id,
                    'CREATED_AT' => $this->timeNow
                ];

                M_LocationStatus::create($log);
            }
        }
    }

    public function checkCollateral(Request $request){
        try {

            if (collect($request->jaminan)->isNotEmpty()) {
                foreach ($request->jaminan as $result) {
                    $checkMethod = $this->checkCollateralExists($result['type'],$result['number']);
                    
                    if ($checkMethod->isNotEmpty()) {
                        return response()->json(['status' => false, 'message' => "ADA JAMINAN YANG AKTIF BANGSAT","data" => $checkMethod->first()->LOAN_NUMBER], 400);
                    }
                }
            }

            return response()->json(['status' => true,'message' =>"Aman Cuk"], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function checkCollateralExists($type, $no)
    {
        $table = $type === 'kendaraan' ? 'cr_collateral' : 'cr_collateral_sertification';
        $column = $type === 'kendaraan' ? 'BPKB_NUMBER' : 'NO_SERTIFIKAT';
        
        return DB::table('credit as a')
                    ->leftJoin($table . ' as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                    ->where('b.' . $column, $no)
                    ->where('a.STATUS', 'A')
                    ->select('a.LOAN_NUMBER')
                    ->get();  
    }

    // private function generateAmortizationSchedule($principal, $angsuran, $setDate, $loanTerm) {
    //     // Calculate the monthly interest rate (converted)
    //     $suku_bunga_konversi = round(excelRate($loanTerm, -$angsuran, $principal) * 100, 10) / 100;

    //     $angsuran_pokok_bunga = $angsuran; // Fixed monthly payment
    //     $schedule = [];
    //     $setDebet = $principal; // Remaining balance at the start
    //     $paymentDate = strtotime($setDate); // Initial payment date
    //     $term = ceil($loanTerm); // Total number of payments (rounded)

    //     for ($i = 1; $i <= $term; $i++) {
    //         // Calculate interest for this installment
    //         $interest = round($setDebet * $suku_bunga_konversi, 2);
    //         // Calculate principal payment
    //         $principalPayment = round($angsuran_pokok_bunga - $interest, 2);

    //         if ($i === $term) {
    //             // Last installment:
    //             // Principal payment is the remaining balance
    //             $principalPayment = round($setDebet, 2);
    //             // Recalculate interest for the last installment based on remaining balance
    //             $interest = round($setDebet * $suku_bunga_konversi, 2);
    //             // Total payment for the last installment is principal + interest
    //             $totalPayment = round($principalPayment + $interest, 2);
    //             // Set remaining balance to 0.00 after the last installment
    //             $setDebet = 0.00;
    //         } else {
    //             // For regular installments, reduce the remaining balance
    //             $setDebet = round($setDebet - $principalPayment, 2);
    //             // Regular total payment is fixed
    //             $totalPayment = $angsuran_pokok_bunga;
    //         }

    //         // Add this installment's data to the schedule
    //         $schedule[] = [
    //             'angsuran_ke' => $i,
    //             'tgl_angsuran' => date('Y-m-d', $paymentDate),
    //             'pokok' => floatval($principalPayment), // Principal payment
    //             'bunga' => floatval($interest), // Interest payment
    //             'total_angsuran' => floatval($totalPayment), // Total payment
    //             'baki_debet' => floatval($setDebet) // Remaining balance
    //         ];

    //         // Move to the next payment date (1 month later)
    //         $paymentDate = strtotime("+1 month", $paymentDate);
    //     }

    //     if ($setDebet > 0) {
    //         $schedule[$term - 1]['pokok'] += round($setDebet, 2);
    //         $schedule[$term - 1]['bunga'] = round($schedule[$term - 1]['total_angsuran'] - $schedule[$term - 1]['pokok'], 2);
    //         $schedule[$term - 1]['baki_debet'] = 0.00;
    //     }

    //     return $schedule;
    // }

    // private function generateAmortizationSchedule($principal, $angsuran, $setDate, $loanTerm)
    // {
    //     // Calculate the monthly interest rate (converted)
    //     $suku_bunga_konversi = round(excelRate($loanTerm, -$angsuran, $principal) * 100, 10) / 100;
    //     $suku_bunga = round((($loanTerm * ($angsuran - ($principal / $loanTerm))) / $principal) * 100, 2);
    //     $ttal_bunga = round(($principal*($suku_bunga/100)/12)*$loanTerm,2);

    //     $schedule = [];
    //     $remainingBalance = $principal;
    //     $paymentDate = strtotime($setDate);
    //     $term = ceil($loanTerm);

    //     for ($i = 1; $i <= $term; $i++) {
    //         // Calculate interest for this installment
    //         $interest = round($remainingBalance * $suku_bunga_konversi, 2);

    //         // Adjust calculation for the last two installments
    //         if ($i >= $term - 1) {
    //             // For the last two installments, calculate differently
    //             if ($i == $term - 1) {
    //                 // Second to last installment
    //                 $principalPayment = $angsuran - $interest;
    //             } else {
    //                 // Last installment
    //                 $principalPayment = $remainingBalance;
    //             }
    //         } else {
    //             // Regular installments
    //             $principalPayment = $angsuran - $interest;
    //         }

    //         // Ensure exact amount and avoid floating point issues
    //         $principalPayment = round($principalPayment, 2);
    //         $totalPayment = $principalPayment + $interest;

    //         // Adjust remaining balance
    //         $remainingBalance = round($remainingBalance - $principalPayment, 2);

    //         // Ensure last installment reaches exactly zero
    //         if ($i == $term) {
    //             $remainingBalance = 0.00;
    //         }

    //         $schedule[] = [
    //             'angsuran_ke' => $i,
    //             'tgl_angsuran' => date('Y-m-d', $paymentDate),
    //             'baki_debet_awal' => floatval($remainingBalance + $principalPayment), // Starting balance
    //             'pokok' => floatval($principalPayment),
    //             'bunga' => floatval($interest),
    //             'total_angsuran' => floatval($totalPayment),
    //             'baki_debet' => floatval($remainingBalance)
    //         ];

    //         $paymentDate = strtotime("+1 month", $paymentDate);
    //     }

    //     return $schedule;
    // }

    // private function generateAmortizationSchedule($principal, $angsuran, $setDate, $loanTerm)
    // {
    //     // Calculate the monthly interest rate (converted)
    //     $suku_bunga_konversi = round(excelRate($loanTerm, -$angsuran, $principal) * 100, 10) / 100;
    //     $suku_bunga = round((($loanTerm * ($angsuran - ($principal / $loanTerm))) / $principal) * 100, 2);
    //     $ttal_bunga = round(($principal * ($suku_bunga / 100) / 12) * $loanTerm, 2); // Total interest for the loan

    //     $schedule = [];
    //     $remainingBalance = $principal;
    //     $paymentDate = strtotime($setDate);
    //     $term = ceil($loanTerm);
    //     $totalInterestPaid = 0; // Track total interest paid

    //     for ($i = 1; $i <= $term; $i++) {
    //         // Regular interest calculation for the current installment
    //         if ($i < $term - 1) {
    //             $interest = round($remainingBalance * $suku_bunga_konversi, 2);
    //         } else {
    //             // Adjust interest calculation for the second to last and last installments
    //             if ($i == $term - 1) {
    //                 // Second to last installment, interest should be the remaining total interest to be paid
    //                 $interest = round($ttal_bunga - $totalInterestPaid, 2);
    //             } else {
    //                 // Last installment, interest should be calculated for the remaining balance
    //                 $interest = round($remainingBalance * $suku_bunga_konversi, 2);
    //             }
    //         }

    //         // Adjust calculation for the second to last installment
    //         if ($i == $term - 1) {
    //             // Second to last installment, principal is adjusted so total payment is exactly the installment amount
    //             $principalPayment = round($angsuran - $interest, 2);
    //             if ($principalPayment > $remainingBalance) {
    //                 // If the principal payment exceeds the remaining balance, adjust it
    //                 $principalPayment = $remainingBalance;
    //             }
    //         } elseif ($i == $term) {
    //             // Last installment, pay off the remaining balance
    //             $principalPayment = $remainingBalance;
    //         } else {
    //             // Regular installments
    //             $principalPayment = round($angsuran - $interest, 2);
    //         }

    //         // Ensure exact amount and avoid floating point issues
    //         $totalPayment = $principalPayment + $interest;

    //         // Adjust remaining balance
    //         $remainingBalance = round($remainingBalance - $principalPayment, 2);

    //         // Accumulate total interest paid
    //         $totalInterestPaid += $interest;

    //         // Ensure last installment reaches exactly zero
    //         if ($i == $term) {
    //             $remainingBalance = 0.00;
    //         }

    //         // Store the installment schedule
    //         $schedule[] = [
    //             'angsuran_ke' => $i,
    //             'tgl_angsuran' => date('Y-m-d', $paymentDate),
    //             'baki_debet_awal' => floatval($remainingBalance + $principalPayment), // Starting balance
    //             'pokok' => floatval($principalPayment),
    //             'bunga' => floatval($interest),
    //             'total_angsuran' => floatval($totalPayment),
    //             'baki_debet' => floatval($remainingBalance)
    //         ];

    //         // Move payment date to the next month
    //         $paymentDate = strtotime("+1 month", $paymentDate);
    //     }

    //     return $schedule;
    // }


    private function generateAmortizationSchedule($setDate, $data)
    {
        $schedule = [];
        $remainingBalance = $data->POKOK_PEMBAYARAN;
        $term = ceil($data->TENOR);
        $angsuran = $data->INSTALLMENT;
        $suku_bunga_konversi = ($data->FLAT_RATE/100);
        $ttal_bunga = $data->TOTAL_INTEREST;
        $totalInterestPaid = 0;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($remainingBalance * $suku_bunga_konversi, 2);

            if ($i < $term) {
                $principalPayment = round($angsuran - $interest, 2);
            } else {
                $principalPayment = round($remainingBalance, 2);
                $interest = round($ttal_bunga - $totalInterestPaid, 2);
            }

            $totalPayment = round($principalPayment + $interest, 2);
            $remainingBalance = round($remainingBalance - $principalPayment, 2);
            $totalInterestPaid += $interest;
            if ($i == $term) {
                $remainingBalance = 0.00;
            }

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => setPaymentDate($setDate, $i), 
                'baki_debet_awal' => floatval($remainingBalance + $principalPayment),
                'pokok' => floatval($principalPayment),
                'bunga' => floatval($interest),
                'total_angsuran' => floatval($totalPayment),
                'baki_debet' => floatval($remainingBalance)
            ];
        }

        return $schedule;
    }



}

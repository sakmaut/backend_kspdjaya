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

            // $data_credit_schedule = generateAmortizationSchedule(7760000, 492000, '2024-09-26', 44, 24);
    
            return response()->json($this->buildData($request,$check), 200);
            // return response()->json( generateCustCode($request, 'credit', 'CUST_CODE'), 200);
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
        $loan_number = generateCode($request, 'credit', 'LOAN_NUMBER');

        $principal = $data->POKOK_PEMBAYARAN;
        // $annualInterestRate = $data->FLAT_RATE;
        $effRate = $data->EFF_RATE;
        $loanTerm = $data->TENOR;
        $angsuran = $data->INSTALLMENT;
        $set_tgl_awal =$request->tgl_awal;

        $data_credit_schedule = generateAmortizationSchedule($principal,$angsuran, $set_tgl_awal,$effRate, $loanTerm);

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
                    'pokok' => number_format($key['PRINCIPAL'], 2),
                    'bunga' => number_format($key['INTEREST'], 2),
                    'total_angsuran' => number_format($key['INSTALLMENT'], 2),
                    'baki_debet' => number_format($key['PRINCIPAL_REMAINS'], 2)
                ];
            }
        }
      
        $SET_UUID = Uuid::uuid7()->toString();
        $cust_code = generateCustCode($request, 'credit', 'CUST_CODE');

        if (!$check_exist && $request->flag == 'yes') {
            $this->insert_credit($SET_UUID,$request, $data, $loan_number,$installment_count, $cust_code);
            $no =1;
            foreach ($data_credit_schedule as $list) {
                $credit_schedule =
                [
                    'ID' => Uuid::uuid7()->toString(),
                    'LOAN_NUMBER' => $loan_number,
                    'INSTALLMENT_COUNT' => $no++,
                    'PAYMENT_DATE' => Carbon::parse($list['tgl_angsuran'])->format('Y-m-d'),
                    'PRINCIPAL' => converttodecimal($list['pokok']),
                    'INTEREST' => converttodecimal($list['bunga']),
                    'INSTALLMENT' => converttodecimal($list['total_angsuran']),
                    'PRINCIPAL_REMAINS' => converttodecimal($list['baki_debet']),
                    'PAID_FLAG' => ''
                ];

                M_CreditSchedule::create($credit_schedule);
            }
            
            $this->insert_customer($request,$data, $cust_code);
            $this->insert_customer_xtra($data, $cust_code);
            $this->insert_collateral($request,$data,$SET_UUID);
            $this->insert_collateral_sertification($request,$data,$SET_UUID);
        }

        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
                                $query->whereNull('DELETED_AT')
                                    ->orWhere('DELETED_AT', '');
                            })->get(); 
        $guarente_sertificat = M_CrGuaranteSertification::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->where(function($query) {
                                    $query->whereNull('DELETED_AT')
                                        ->orWhere('DELETED_AT', '');
                                })->get(); 

        $data = [
            "no_perjanjian" => !$check_exist? $loan_number:$check_exist->LOAN_NUMBER,
            "cabang" => 'CABANG '.strtoupper($pihak1->name)??null,
            "kota" => strtoupper($pihak1->city)??null,
            "tgl_cetak" => !empty($check_exist)? Carbon::parse($check_exist->CREATED_AT)->format('Y-m-d') : null,
            "tgl_awal_angsuran" => !empty($check_exist)? Carbon::parse($check_exist->INSTALLMENT_DATE)->format('Y-m-d'):null,
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
             "tgl_awal_pk" => !empty($check_exist)?Carbon::parse($check_exist->ENTRY_DATE)->format('Y-m-d'):Carbon::parse($set_tgl_awal)->format('Y-m-d'),
             "tgl_akhir_pk" => !empty($check_exist)?Carbon::parse($check_exist->END_DATE)->format('Y-m-d'):add_months($set_tgl_awal,$loanTerm),
             "angsuran" =>bilangan($angsuran)??null,
             "opt_periode" => $data->OPT_PERIODE??null,
             "jaminan" => [],
             "struktur" => $check_exist != null && !empty($check_exist->LOAN_NUMBER)?$schedule:$data_credit_schedule??null
            // "struktur" => $data_credit_schedule ?? null
        ];

        foreach ($cr_guarantor as $list) {
            $arrayList['penjamin'][] = [
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

        $data_credit =[
            'ID' =>  $SET_UUID,
            'LOAN_NUMBER' => $loan_number,
            'STATUS_REC' => $data->BRANCH,
            'BRANCH'   => $data->BRANCH,
            'CUST_CODE' => $cust_code,
            'ORDER_NUMBER' => $data->ORDER_NUMBER,
            'STATUS'  => 'A',
            'MCF_ID'  => $survey->created_by??null,
            'ENTRY_DATE'  => $request->tgl_awal??null,
            'FIRST_ARR_DATE'  => null,
            'INSTALLMENT_DATE'  => $request->tgl_awal??null,
            'END_DATE'  => add_months($request->tgl_awal,$data->PERIOD)??null,
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

        $credit = M_Credit::create($data_credit);
        $last_id = $credit->id;

        return $last_id;
    }

    private function insert_customer($request,$data, $cust_code){

        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID',$data->ID)->first();

        $check_customer_ktp = M_Customer::where('ID_NUMBER',$cr_personal->ID_NUMBER)->first();

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
            M_Customer::create($data_customer);
        }else{
            $check_customer_ktp->update($data_customer);
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
        $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->get();

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
                    'LOCATION_BRANCH' => '',
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];
    
                M_CrCollateral::create($data_jaminan);
            }
        }
    }

    private function insert_collateral_sertification($request,$data,$lastID){
        $data_collateral = M_CrGuaranteSertification::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->get();

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
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];
    
                M_CrCollateralSertification::create($data_jaminan);
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
}

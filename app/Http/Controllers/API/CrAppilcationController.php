<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_ApplicationApproval;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationBank;
use App\Models\M_CrApplicationGuarantor;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_Credit;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_CustomerExtra;
use App\Models\M_SurveyApproval;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CrAppilcationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function showKapos(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request,'0:draft');
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function showHo(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request,'0:draft', '6:closed kapos');
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function show(Request $request,$id)
    {
        try {
            $check = M_CrApplication::where('CR_SURVEY_ID',$id)->whereNull('deleted_at')->first();

            if (!$check) {
                $check_application_id = M_CrApplication::where('ID',$id)->whereNull('deleted_at')->first();
            }else {
                $check_application_id = $check;
            }
            
            $surveyID = $check_application_id->CR_SURVEY_ID;

            if (!isset($surveyID)  || $surveyID == '') {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $detail_prospect = M_CrSurvey::where('id',$surveyID)->first();

            return response()->json(['message' => 'OK',"status" => 200,'response' =>  self::resourceDetail($detail_prospect,$check_application_id)], 200);
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

            $check_prospect_id = M_CrSurvey::where('id',$request->data_order['cr_prospect_id'])
                                                ->whereNull('deleted_at')->first();

            if (!$check_prospect_id) {
                throw new Exception("Id Kunjungan Is Not Exist", 404);
            }

            // self::insert_cr_application($request,$uuid);
            // // self::update_cr_prospect($request,$check_prospect_id);
            // self::insert_cr_personal($request,$uuid);
            // self::insert_cr_personal_extra($request,$uuid);
            self::insert_bank_account($request,$uuid);
    
            DB::commit();
            // ActivityLogger::logActivity($request,"Success",200); 
            return response()->json(['message' => 'Application created successfully',"status" => 200], 200);
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

    public function update(Request $request,$id)
    {
        try {
            $request->validate([
                'flag_pengajuan' => 'required|string',
            ]);

            $check_application_id = M_CrApplication::find($id);

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $surveyID = $check_application_id->CR_SURVEY_ID;
            

            self::insert_cr_application($request,$check_application_id);
            self::insert_cr_personal($request,$id);
            self::insert_cr_order($request, $surveyID,$id);
            self::insert_cr_personal_extra($request,$id);
            if (!empty($request->penjamin)) {
                self::insert_cr_guarantor($request, $id);
            }
            if (!empty($request->pasangan)) {
                self::insert_cr_spouse($request, $id);
            }
            self::insert_bank_account($request,$id);
            self::insert_taksasi($request, $surveyID);
            self::insert_application_approval($id, $surveyID,$request->flag_pengajuan);

            if($request->user()->position === 'KAPOS'){
                $data_approval['application_result'] = '7:resubmit kapos';
    
                $approval_change = M_SurveyApproval::where('CR_SURVEY_ID', $surveyID)->first();

                if ($approval_change) {
                    $approval_change->update(['APPROVAL_RESULT' => '7:resubmit kapos']);
                    $approvalLog = new ApprovalLog();
                    $approvalLog->surveyApprovalLog("AUTO_APPROVED_BY_SYSTEM", $approval_change->ID, '7:resubmit kapos');
                }

                $checkApproval= M_ApplicationApproval::where('cr_application_id', $id)->first();
    
                $checkApproval->update($data_approval);
            }

            return response()->json(['message' => 'Updated Successfully',"status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function insert_cr_application($request,$applicationModel){

        $data_cr_application =[
            'FORM_NUMBER' => '',
            'CUST_CODE' => '',
            'ENTRY_DATE' => Carbon::now()->format('Y-m-d'),
            'SUBMISSION_VALUE' => $request->ekstra['nilai_yang_diterima']??null,
            'CREDIT_TYPE' => $request->ekstra['tipe_angsuran']??null,
            'INSTALLMENT_COUNT' =>null,
            'PERIOD' => $request->ekstra['periode']??null,
            'INSTALLMENT' => $request->ekstra['angsuran']??null,
            'OPT_PERIODE' =>$request->ekstra['opt_periode']??null,
            'FLAT_RATE' => $request->ekstra['bunga_flat']??null,
            'EFF_RATE' => $request->ekstra['eff_rate']??null,
            'INSTALLMENT_TYPE' => $request->ekstra['jenis_angsuran']??null,
            'TENOR' => $request->ekstra['tenor']??null,
            // 'ACC_VALUE' => $request->ekstra['nilai_yang_diterima']??null,
            'POKOK_PEMBAYARAN' => $request->ekstra['pokok_pembayaran']??null,
            'NET_ADMIN' => $request->ekstra['net_admin']??null,
            'TOTAL_ADMIN' => $request->ekstra['total']??null,
            'CADANGAN' => $request->ekstra['cadangan']??null,
            'PAYMENT_WAY'=> $request->ekstra['cara_pembayaran']??null,
            'PROVISION'=> $request->ekstra['provisi']??null,
            'INSURANCE'=> $request->ekstra['asuransi']??null,
            'TRANSFER_FEE'=> $request->ekstra['biaya_transfer']??null,
            'INTEREST_MARGIN'=> $request->ekstra['bunga_margin']??null,
            'PRINCIPAL_MARGIN'=> $request->ekstra['pokok_margin']??null,
            'LAST_INSTALLMENT'=> $request->ekstra['angsuran_terakhir']??null,
            'INTEREST_MARGIN_EFF_ACTUAL'=> $request->ekstra['bunga_eff_actual']??null,
            'INTEREST_MARGIN_EFF_FLAT'=> $request->ekstra['bunga_flat']??null,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        if(!$applicationModel){
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['BRANCH'] = $request->user()->branch_id;
            $data_cr_application['ORDER_NUMBER'] = $this->createAutoCode(M_CrApplication::class,'ORDER_NUMBER','FPK');
            M_CrApplication::create($data_cr_application);
        }else{
            // compareData(M_CrApplication::class,$id,$data_cr_application,$request);
            $applicationModel->update($data_cr_application);
        } 
    }

    private function insert_cr_order($request,$id,$fpkId){

        $check = M_CrOrder::where('APPLICATION_ID',$fpkId)->first();

        $data_order =[
            'NO_NPWP' => $request->order['no_npwp']??null,
            'BIAYA' => $request->order['biaya_bulanan']??null,
            'ORDER_TANGGAL' => $request->order['order_tanggal']??null,
            'ORDER_STATUS' => $request->order['order_status']??null,
            'ORDER_TIPE' => $request->order['order_tipe']??null,
            'UNIT_BISNIS' => $request->order['unit_bisnis']??null,
            'CUST_SERVICE' => $request->order['cust_service']??null,
            'REF_PELANGGAN' => $request->order['ref_pelanggan']??null,
            'PROG_MARKETING' => $request->order['prog_marketing']??null,
            'CARA_BAYAR' => $request->order['cara_bayar']??null,
            'KODE_BARANG' => $request->barang_taksasi['kode_barang']??null,
            'ID_TIPE' => $request->barang_taksasi['id_tipe']??null,
            'TAHUN' => $request->barang_taksasi['tahun']??null,
            'HARGA_PASAR' => $request->barang_taksasi['harga_pasar']??null,
            'MOTHER_NAME' => $request->order['nama_ibu'] ?? null,
            'CATEGORY' => $request->order['kategori'] ?? null,
            'TITLE' => $request->order['gelar'] ?? null,
            'WORK_PERIOD'  => $request->order['lama_bekerja'] ?? null,
            'DEPENDANTS'  => $request->order['tanggungan'] ?? null,
            'INCOME_PERSONAL'  => $request->order['pendapatan_pribadi'] ?? null,
            'INCOME_SPOUSE'  => $request->order['pendapatan_pasangan'] ?? null,
            'INCOME_OTHER'  => $request->order['pendapatan_lainnya'] ?? null,
            'EXPENSES'  => $request->order['biaya_bulanan'] ?? null
        ];

        if(!$check){
            $data_order['ID'] = Uuid::uuid7()->toString();
            $data_order['APPLICATION_ID'] = $fpkId;

            M_CrOrder::create($data_order);
        }else{
            $check->update($data_order);
        }
    }

    private function insert_cr_personal($request,$applicationId){

        $check = M_CrPersonal::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'NAME' => $request->pelanggan['nama']??null,
            'ALIAS' => $request->pelanggan['nama_panggilan']??null,
            'GENDER' => $request->pelanggan['jenis_kelamin']??null,
            'BIRTHPLACE' => $request->pelanggan['tempat_lahir']??null,
            'BIRTHDATE' => $request->pelanggan['tgl_lahir']??null,
            'BLOOD_TYPE' => $request->pelanggan['gol_darah']??null,
            'MARTIAL_STATUS' => $request->pelanggan['status_kawin']??null,
            'MARTIAL_DATE' => $request->pelanggan['tgl_kawin']??null,
            'ID_TYPE' => $request->pelanggan['tipe_identitas']??null,
            'ID_NUMBER' => $request->pelanggan['no_identitas']??null,
            'ID_ISSUE_DATE' => $request->pelanggan['tgl_terbit']??null,
            'ID_VALID_DATE' => $request->pelanggan['masa_berlaku']??null,
            'KK' => $request->pelanggan['no_kk']??null,
            'CITIZEN' => $request->pelanggan['warganegara']??null,
            
            'ADDRESS' => $request->alamat_identitas['alamat']??null,
            'RT' => $request->alamat_identitas['rt']??null,
            'RW' => $request->alamat_identitas['rw']??null,
            'PROVINCE' => $request->alamat_identitas['provinsi']??null,
            'CITY' => $request->alamat_identitas['kota']??null,
            'KELURAHAN' => $request->alamat_identitas['kelurahan']??null,
            'KECAMATAN' => $request->alamat_identitas['kecamatan'],
            'ZIP_CODE' =>  $request->alamat_identitas['kode_pos'],

            'INS_ADDRESS' => $request->alamat_tagih['alamat']??null,
            'INS_RT' => $request->alamat_tagih['rt']??null,
            'INS_RW' => $request->alamat_tagih['rw']??null,
            'INS_PROVINCE' => $request->alamat_tagih['provinsi']??null,
            'INS_CITY' => $request->alamat_tagih['kota']??null,
            'INS_KELURAHAN' => $request->alamat_tagih['kelurahan']??null,
            'INS_KECAMATAN' => $request->alamat_tagih['kecamatan']??null,
            'INS_ZIP_CODE' => $request->alamat_tagih['kode_pos']??null,

            'OCCUPATION' => $request->pekerjaan['pekerjaan']??null,
            'OCCUPATION_ON_ID' => $request->pekerjaan['pekerjaan_id']??null,
            'RELIGION' => $request->pekerjaan['agama']??null,
            'EDUCATION' => $request->pekerjaan['pendidikan']??null,
            'PROPERTY_STATUS' => $request->pekerjaan['status_rumah']??null,
            'PHONE_HOUSE' => $request->pekerjaan['telepon_rumah']??null,
            'PHONE_PERSONAL' => $request->pekerjaan['telepon_selular']??null,
            'PHONE_OFFICE' => $request->pekerjaan['telepon_kantor']??null,
            'EXT_1' => $request->pekerjaan['ekstra1']??null,
            'EXT_2' => $request->pekerjaan['ekstra2']??null,
           
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        if(!$check){
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;
            $data_cr_application['CUST_CODE'] = generateCode($request, 'cr_personal', 'CUST_CODE');
    
            M_CrPersonal::create($data_cr_application);
        }else{
            $check->update($data_cr_application);
        }
    }

    private function insert_cr_guarantor($request,$applicationId){

        $check = M_CrApplicationGuarantor::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'NAME' => $request->penjamin['nama']??null,
            'GENDER' => $request->penjamin['jenis_kelamin']??null,
            'BIRTHPLACE' => $request->penjamin['tempat_lahir']??null,
            'BIRTHDATE' => $request->penjamin['tgl_lahir']??null,
            'ADDRESS' => $request->penjamin['alamat']??null,
            // .' '.$request->penjamin['rt']??null.'/'.$request->penjamin['rw']??null
            //             .' '.$request->penjamin['kota']??null.' '.$request->penjamin['kecamatan']??null.' '.
            //             $request->penjamin['kelurahan']??null.' '.$request->penjamin['provinsi']??null.' '.$request->penjamin['kode_pos']??null,
            'IDENTITY_TYPE' => $request->penjamin['tipe_identitas']??null,
            'NUMBER_IDENTITY' => $request->penjamin['no_identitas']??null,
            'OCCUPATION' => $request->penjamin['pekerjaan']??null,
            'WORK_PERIOD' => $request->penjamin['lama_bekerja']??null,
            'STATUS_WITH_DEBITUR' => $request->penjamin['hub_cust']??null,
            'MOBILE_NUMBER' => $request->penjamin['no_hp']??null,
            'INCOME' => $request->penjamin['pendapatan']??null,
        ];

        if(!$check){
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;
    
            M_CrApplicationGuarantor::create($data_cr_application);
        }else{
            $check->update($data_cr_application);
        }
    }

    private function insert_cr_spouse($request,$applicationId){

        $check = M_CrApplicationSpouse::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'NAME' => $request->pasangan['nama_pasangan']??null,
            'BIRTHPLACE' => $request->pasangan['tmptlahir_pasangan']??null,
            'BIRTHDATE' => $request->pasangan['tgllahir_pasangan']??null,
            'ADDRESS' => $request->pasangan['alamat_pasangan']??null,
            'OCCUPATION' => $request->pasangan['pekerjaan_pasangan']??null
        ];

        if(!$check){
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;
    
            M_CrApplicationSpouse::create($data_cr_application);
        }else{
            $check->update($data_cr_application);
        }
    }

    private function insert_cr_personal_extra($request,$applicationId){

        $check = M_CrPersonalExtra::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'BI_NAME' => $request->tambahan['nama_bi']??null,
            'EMAIL' => $request->tambahan['email']??null,
            'INFO' => $request->tambahan['info_khusus']??null,
            'OTHER_OCCUPATION_1' => $request->tambahan['usaha_lain1']??null,
            'OTHER_OCCUPATION_2' => $request->tambahan['usaha_lain2']??null,
            'OTHER_OCCUPATION_3' => $request->tambahan['usaha_lain3']??null,
            'OTHER_OCCUPATION_4' => $request->tambahan['usaha_lain4']??null,
            'MAIL_ADDRESS' => $request->surat['alamat']??null,
            'MAIL_RT' => $request->surat['rt']??null,
            'MAIL_RW' => $request->surat['rw']??null,
            'MAIL_PROVINCE' => $request->surat['provinsi']??null,
            'MAIL_CITY' => $request->surat['kota']??null,
            'MAIL_KELURAHAN' => $request->surat['kelurahan']??null,
            'MAIL_KECAMATAN' => $request->surat['kecamatan']??null,
            'MAIL_ZIP_CODE' => $request->surat['kode_pos']??null,
            'EMERGENCY_NAME' => $request->kerabat_darurat['nama']??null,
            'EMERGENCY_ADDRESS' => $request->kerabat_darurat['alamat']??null,
            'EMERGENCY_RT' => $request->kerabat_darurat['rt']??null,
            'EMERGENCY_RW' => $request->kerabat_darurat['rw']??null,
            'EMERGENCY_PROVINCE' => $request->kerabat_darurat['provinsi']??null,
            'EMERGENCY_CITY' => $request->kerabat_darurat['kota']??null,
            'EMERGENCY_KELURAHAN' => $request->kerabat_darurat['kelurahan']??null,
            'EMERGENCY_KECAMATAN' => $request->kerabat_darurat['kecamatan']??null,
            'EMERGENCY_ZIP_CODE' => $request->kerabat_darurat['kode_pos']??null,
            'EMERGENCY_PHONE_HOUSE' => $request->kerabat_darurat['no_telp']??null,
            'EMERGENCY_PHONE_PERSONAL'  => $request->kerabat_darurat['no_hp']??null
        ];

        if(!$check){
            $data_cr_application['ID'] = Uuid::uuid4()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;

            M_CrPersonalExtra::create($data_cr_application);
        }else{
            $check->update($data_cr_application);
        }
    }

    private function insert_bank_account($request,$applicationId){

        if (isset($request->info_bank) && is_array($request->info_bank)) {

            M_CrApplicationBank::where('APPLICATION_ID', $applicationId)->delete();

            $dataToInsert = [];
            foreach ($request->info_bank as $result) {
                $dataToInsert[] = [
                    'ID' => Uuid::uuid4()->toString(),
                    'APPLICATION_ID' => $applicationId,
                    'BANK_CODE' => $result['kode_bank']?? null,
                    'BANK_NAME' => $result['nama_bank']?? null,
                    'ACCOUNT_NUMBER' => $result['no_rekening']?? null,
                    'ACCOUNT_NAME' => $result['atas_nama']?? null,
                    'STATUS' => $result['status']?? null,
                ];
            }

            M_CrApplicationBank::insert($dataToInsert);
        }
    }

    private function insert_application_approval($applicationId, $surveyID,$flag){
        if($flag === 'yes'){
            $data_approval['application_result'] = '1:waiting kapos';

            $approval_change = M_SurveyApproval::where('CR_SURVEY_ID', $surveyID)->first();
            if ($approval_change) {
                $approval_change->update(['APPROVAL_RESULT' => '3:waiting kapos']);
                $approvalLog = new ApprovalLog();
                $approvalLog->surveyApprovalLog("AUTO_APPROVED_BY_SYSTEM", $approval_change->ID, '3:waiting kapos');
            }
        }else{
            $data_approval['application_result'] = '0:draft';
        }

        $checkApproval= M_ApplicationApproval::where('cr_application_id', $applicationId)->first();

        if (!$checkApproval) {
            $data_approval = array_merge(['ID' => Uuid::uuid7()->toString(), 'cr_application_id' => $applicationId], $data_approval);
            M_ApplicationApproval::create($data_approval);
        } else {
            $checkApproval->update($data_approval);
        }
    }

    public function generateUuidFPK(Request $request)
    {
        try {
            $getSurveyId = $request->cr_prospect_id;

            $check_survey_id = M_CrSurvey::where('id',$getSurveyId)->whereNull('deleted_at')->first();

            if (!$check_survey_id) {
                throw new Exception("Id Survey Is Not Exist", 404);
            }

            $uuid = Uuid::uuid7()->toString();

            $check_prospect_id = M_CrApplication::where('CR_SURVEY_ID',$getSurveyId)->first();

            if(!$check_prospect_id){
                $generate_uuid = $uuid;

                $data_cr_application =[
                    'ID' => $uuid,
                    'CR_SURVEY_ID' => $check_survey_id->id,
                    'ORDER_NUMBER' => $this->createAutoCode(M_CrApplication::class,'ORDER_NUMBER','FPK'),
                    'BRANCH' => $request->user()->branch_id,
                    'VERSION' => 1,
                    'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                    'CREATE_USER' => $request->user()->id,
                ];
        
                $fpkCreate =  M_CrApplication::create($data_cr_application);

                $data_approval_fpk =[
                    'id' => Uuid::uuid7()->toString(),
                    'cr_application_id' => $fpkCreate->ID,
                    'application_result' => '0:draft'
                ];

                M_ApplicationApproval::create($data_approval_fpk);

                $customer = M_Customer::where('ID_NUMBER',$check_survey_id->ktp)->first();
                $customer_xtra = M_CustomerExtra::where('CUST_CODE',$customer->CUST_CODE)->first();

                if(strtolower($check_survey_id->category) == 'ro'){
                    $data_cr_personal =[  
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'CUST_CODE' => generateCode($request, 'cr_personal', 'CUST_CODE'),
                        'NAME' => $check_survey_id->nama??null,
                        'ALIAS' => $customer->ALIAS??null,
                        'GENDER' => $customer->GENDER??null,
                        'BIRTHPLACE' => $customer->BIRTHPLACE??null,
                        'BIRTHDATE' => $check_survey_id->tgl_lahir??null,
                        'BLOOD_TYPE' => $customer->BLOOD_TYPE??null,
                        'MARTIAL_STATUS' => $customer->MARTIAL_STATUS??null,
                        'MARTIAL_DATE' => $customer->MARTIAL_DATE??null,
                        'ID_TYPE' => $customer->ID_TYPE??null,
                        'ID_NUMBER' => $check_survey_id->ktp??null,
                        'ID_ISSUE_DATE' => $customer->ID_ISSUE_DATE??null,
                        'ID_VALID_DATE' => $customer->ID_VALID_DATE??null,
                        'KK' => $check_survey_id->kk??null,
                        'CITIZEN' => $customer->CITIZEN??null,
                        
                        'ADDRESS' => $check_survey_id->alamat,
                        'RT' => $check_survey_id->rt,
                        'RW' => $check_survey_id->rw,
                        'PROVINCE' => $check_survey_id->province,
                        'CITY' => $check_survey_id->city,
                        'KELURAHAN' => $check_survey_id->kelurahan,
                        'KECAMATAN' => $check_survey_id->kecamatan,
                        'ZIP_CODE' => $check_survey_id->zip_code,
            
                        'INS_ADDRESS' => $customer->INS_ADDRESS??null,
                        'INS_RT' => $customer->INS_RT??null,
                        'INS_RW' => $customer->INS_RW??null,
                        'INS_PROVINCE' => $customer->INS_PROVINCE??null,
                        'INS_CITY' => $customer->INS_CITY??null,
                        'INS_KELURAHAN' => $customer->INS_KELURAHAN??null,
                        'INS_KECAMATAN' => $customer->INS_KECAMATAN??null,
                        'INS_ZIP_CODE' => $customer->INS_ZIP_CODE??null,
            
                        'OCCUPATION' => $check_survey_id->usaha??null,
                        'OCCUPATION_ON_ID' => $check_survey_id->sector??null,
                        'RELIGION' => $customer->RELIGION??null,
                        'EDUCATION' => $customer->EDUCATION??null,
                        'PROPERTY_STATUS' => $customer->PROPERTY_STATUS??null,
                        'PHONE_HOUSE' => $customer->PHONE_HOUSE??null,
                        'PHONE_PERSONAL' => $check_survey_id->hp??null,
                        'PHONE_OFFICE' => $customer->PHONE_OFFICE??null,
                        'EXT_1' => $customer->EXT_1??null,
                        'EXT_2' => $customer->EXT_2??null,
                        'VERSION' => 1,
                        'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                        'CREATE_USER' => $request->user()->id,
                    ];

                    $data_cr_order =[
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'NO_NPWP' => $customer->NPWP??null,
                        'MOTHER_NAME' => $customer->MOTHER_NAME ?? null,
                        'WORK_PERIOD'  => $check_survey_id->work_period ?? null,
                        'INCOME_PERSONAL'  => $check_survey_id->income_personal ?? null,
                        'INCOME_SPOUSE'  => $check_survey_id->income_spouse ?? null,
                        'INCOME_OTHER'  => $check_survey_id->income_other ?? null,
                        'UNIT_BISNIS' => $check_survey_id->usaha??null,
                        'EXPENSES'  =>  $check_survey_id->expenses?? null,                    
                    ];  
                    
                    $data_cr_application_spouse =[
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'NAME' => $customer_xtra->SPOUSE_NAME??null,
                        'BIRTHPLACE' => $customer_xtra->SPOUSE_BIRTHPLACE??null,
                        'BIRTHDATE' => $customer_xtra->SPOUSE_BIRTHDATE??null,
                        'ADDRESS' => $customer_xtra->SPOUSE_ADDRESS??null,
                        'OCCUPATION' => $customer_xtra->SPOUSE_OCCUPATION??null
                    ];

                    $data_cr_personal_extra = [
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'EMERGENCY_NAME' => $customer_xtra->EMERGENCY_NAME ?? null,
                        'EMERGENCY_ADDRESS' => $customer_xtra->EMERGENCY_ADDRESS ?? null,
                        'EMERGENCY_RT' => $customer_xtra->EMERGENCY_RT ?? null,
                        'EMERGENCY_RW' => $customer_xtra->EMERGENCY_RW ?? null,
                        'EMERGENCY_PROVINCE' => $customer_xtra->EMERGENCY_PROVINCE ?? null,
                        'EMERGENCY_CITY' => $customer_xtra->EMERGENCY_CITY ?? null,
                        'EMERGENCY_KELURAHAN' => $customer_xtra->EMERGENCY_KELURAHAN ?? null,
                        'EMERGENCY_KECAMATAN' => $customer_xtra->EMERGENCY_KECAMATAN ?? null,
                        'EMERGENCY_ZIP_CODE' => $$customer_xtra->EMERGENCY_ZIP_CODE ?? null,
                        'EMERGENCY_PHONE_HOUSE' => $customer_xtra->EMERGENCY_PHONE_HOUSE ?? null,
                        'EMERGENCY_PHONE_PERSONAL' => $customer_xtra->EMERGENCY_PHONE_PERSONAL ?? null
                    ];    
                
                    M_CrPersonal::create($data_cr_personal);
                    M_CrPersonalExtra::create($data_cr_personal_extra);
                    M_CrOrder::create($data_cr_order);
                    M_CrApplicationSpouse::create($data_cr_application_spouse);
                }
            }else{
                $generate_uuid = $check_prospect_id->ID;
            }

            $approval_change = M_SurveyApproval::where('CR_SURVEY_ID',$getSurveyId)->first();

            $data_update_approval=[
                'APPROVAL_RESULT' => '2:created_fpk'
            ];

            $approval_change->update($data_update_approval);

            $approvalLog = new ApprovalLog();
            $approvalLog->surveyApprovalLog($request->user()->id, $getSurveyId, '2:created_fpk');

            return response()->json(['message' => 'OK',"status" => 200,'response' => ['uuid'=>$generate_uuid]], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function resourceDetail($data,$application)
    {
        $surveyId = $data->id;
        $setApplicationId = $application->ID;

        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$surveyId)->get(); 
        $approval_detail = M_ApplicationApproval::where('cr_application_id',$setApplicationId)->first();
        $attachment_data = M_CrSurveyDocument::where('CR_SURVEY_ID',$surveyId )->get();
        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$setApplicationId)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID',$setApplicationId)->first();
        $cr_oder = M_CrOrder::where('APPLICATION_ID',$setApplicationId)->first();
        $applicationDetail = M_CrApplication::where('ID',$setApplicationId)->first();
        $cr_survey= M_CrSurvey::where('id',$surveyId)->first();
        $check_exist = M_Credit::where('ORDER_NUMBER', $application->ORDER_NUMBER)->first();
        $cr_guarantor = M_CrApplicationGuarantor::where('APPLICATION_ID',$setApplicationId)->first();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID',$setApplicationId)->first();
        $approval = M_ApplicationApproval::where('cr_application_id',$setApplicationId)->first();

        $arrayList = [
            'id_application' => $setApplicationId,
            'order_number' => $application->ORDER_NUMBER,
            'cust_code' => $application->ORDER_NUMBER,
            "flag" => !$check_exist?0:1,
            'pelanggan' =>[
                "nama" => $cr_personal->NAME ?? ( $data->nama?? ''),
                "nama_panggilan" => $cr_personal->ALIAS ?? null,
                "jenis_kelamin" => $cr_personal->GENDER ?? null,
                "tempat_lahir" => $cr_personal->BIRTHPLACE??null,
                "tgl_lahir" => empty($cr_personal->BIRTHDATE)?$cr_survey->tgl_lahir:$cr_personal->BIRTHDATE,
                "gol_darah" => $cr_personal->BLOOD_TYPE??null,
                "status_kawin" => $cr_personal->MARTIAL_STATUS??null,
                "tgl_kawin" => $cr_personal->MARTIAL_DATE ?? null,
                "tipe_identitas" => $cr_personal->ID_TYPE??null,
                "no_identitas" => empty($cr_personal->ID_NUMBER)?$data->ktp:$cr_personal->ID_NUMBER,
                "tgl_terbit_identitas" => $cr_personal->ID_ISSUE_DATE ??null,
                "masa_berlaku_identitas" => $cr_personal->ID_VALID_DATE ?? null,
                "no_kk" => empty($cr_personal->KK)?$cr_survey->kk:$cr_personal->KK,
                "warganegara" => $cr_personal->CITIZEN??null
            ],
            'alamat_identitas' =>[
                "alamat" => empty($cr_personal->ADDRESS)?$cr_survey->alamat:$cr_personal->ADDRESS,
                "rt" =>empty($cr_personal->RT)?$cr_survey->rt:$cr_personal->RT,
                "rw" =>empty($cr_personal->RW)?$cr_survey->rw:$cr_personal->RW,
                "provinsi" =>empty($cr_personal->PROVINCE)?$cr_survey->privince:$cr_personal->PROVINCE,
                "kota" =>empty($cr_personal->CITY)?$cr_survey->city:$cr_personal->CITY,
                "kelurahan" =>empty($cr_personal->KELURAHAN)?$cr_survey->kelurahan:$cr_personal->KELURAHAN,
                "kecamatan" =>empty($cr_personal->KECAMATAN)?$cr_survey->kecamatan:$cr_personal->KECAMATAN,
                "kode_pos" => empty($cr_personal->ZIP_CODE)?$cr_survey->zip_code:$cr_personal->ZIP_CODE
            ],
            'alamat_tagih' =>[
                "alamat" => $cr_personal->INS_ADDRESS??null,
                "rt" => $cr_personal->INS_RT??null,
                "rw" => $cr_personal->INS_RW??null,
                "provinsi" => $cr_personal->INS_PROVINCE??null,
                "kota" => $cr_personal->INS_CITY??null,
                "kelurahan" => $cr_personal->INS_KELURAHAN??null,
                "kecamatan" => $cr_personal->INS_KECAMATAN??null,
                "kode_pos" => $cr_personal->INS_ZIP_CODE??null
            ],
            "barang_taksasi"=>[
                "kode_barang"=>$cr_oder->KODE_BARANG??null,
                "id_tipe"=>$cr_oder->ID_TIPE??null,
                "tahun"=>$cr_oder->TAHUN??null,
                "harga_pasar"=>$cr_oder->HARGA_PASAR??null
            ],
            'pekerjaan' =>[
                "pekerjaan" => empty($cr_personal->OCCUPATION)?$cr_survey->usaha:$cr_personal->OCCUPATION,
                "pekerjaan_id" => empty($cr_personal->OCCUPATION_ON_ID)?$cr_survey->sector:$cr_personal->OCCUPATION_ON_ID,
                "agama" => $cr_personal->RELIGION??null,
                "pendidikan" => $cr_personal->EDUCATION??null,
                "status_rumah" => $cr_personal->PROPERTY_STATUS??null,
                "telepon_rumah" => $cr_personal->PHONE_HOUSE??null,
                "telepon_selular" => empty($cr_personal->PHONE_PERSONAL)?$data->hp:$cr_personal->PHONE_PERSONAL,
                "telepon_kantor" => $cr_personal->PHONE_OFFICE??null,
                "ekstra1" => $cr_personal->EXT_1??null,
                "ekstra2" => $cr_personal->EXT_2??null
            ],
            'order' =>[
                "nama_ibu" => $cr_oder->MOTHER_NAME ?? null, 
                'cr_prospect_id' => $prospect_id??null,
                "kategori" => $cr_oder->CATEGORY ?? null, 
                "gelar" => $cr_oder->TITLE ?? null, 
                "lama_bekerja" => empty($cr_oder->WORK_PERIOD)?$cr_survey->work_period:$cr_oder->WORK_PERIOD, 
                "tanggungan" => $cr_oder->DEPENDANTS ?? null, 
                "biaya_bulanan" =>intval(empty($cr_oder->BIAYA)?$cr_survey->expenses:$cr_oder->BIAYA), 
                "pendapatan_pribadi" => intval(empty($cr_oder->INCOME_PERSONAL)?$cr_survey->income_personal:$cr_oder->INCOME_PERSONAL),
                "pendapatan_pasangan" =>intval(empty($cr_oder->INCOME_SPOUSE)?$cr_survey->income_spouse:$cr_oder->INCOME_SPOUSE),
                "pendapatan_lainnya" =>intval(empty($cr_oder->INCOME_OTHER)?$cr_survey->income_other:$cr_oder->INCOME_OTHER),
                "no_npwp" => $cr_oder->NO_NPWP??null,
                "order_tanggal" =>  $cr_oder->ORDER_TANGGAL??null,
                "order_status" =>  $cr_oder->ORDER_STATUS??null,
                "order_tipe" =>  $cr_oder->ORDER_TIPE??null,
                "unit_bisnis" => $cr_oder->UNIT_BISNIS??null, 
                "cust_service" => $cr_oder->CUST_SERVICE??null,
                "ref_pelanggan" => $cr_oder->REF_PELANGGAN??null,
                "surveyor_name" => User::find($cr_survey->created_by)->fullname,
                "catatan_survey" => $data->survey_note??null,
                "prog_marketing" => $cr_oder->PROG_MARKETING??null,
                "cara_bayar" => $cr_oder->CARA_BAYAR??null
            ],
            'tambahan' =>[
                "nama_bi"  => $cr_personal_extra->BI_NAME ?? null, 
                "email"  => $cr_personal_extra->EMAIL?? null,
                "info_khusus"  => $cr_personal_extra->INFO?? null,
                "usaha_lain1"  => $cr_personal_extra->OTHER_OCCUPATION_1?? null,
                "usaha_lain2"  => $cr_personal_extra->OTHER_OCCUPATION_2?? null,
                "usaha_lain3"  => $cr_personal_extra->OTHER_OCCUPATION_3?? null,
                "usaha_lain4"  => $cr_personal_extra->OTHER_OCCUPATION_4?? null,
            ],
            'kerabat_darurat' =>[
                "nama"  => $cr_personal_extra->EMERGENCY_NAME?? null,
                "alamat"  => $cr_personal_extra->EMERGENCY_ADDRESS?? null,
                "rt"  => $cr_personal_extra->EMERGENCY_RT?? null,
                "rw"  => $cr_personal_extra->EMERGENCY_RW?? null,
                "provinsi" =>$cr_personal_extra->EMERGENCY_PROVINCE?? null,
                "kota" => $cr_personal_extra->EMERGENCY_CITY?? null,
                "kelurahan" => $cr_personal_extra->EMERGENCY_KELURAHAN?? null,
                "kecamatan" => $cr_personal_extra->EMERGENCY_KECAMATAN?? null,
                "kode_pos" => $cr_personal_extra->EMERGENCY_ZIP_CODE?? null,
                "no_telp" => $cr_personal_extra->EMERGENCY_PHONE_HOUSE?? null,
                "no_hp" => $cr_personal_extra->EMERGENCY_PHONE_PERSONAL?? null, 
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
            "pasangan" => [
                "nama_pasangan" =>$cr_spouse->NAME ?? null,
                "tmptlahir_pasangan" =>$cr_spouse->BIRTHPLACE ?? null,
                "pekerjaan_pasangan" => $cr_spouse->OCCUPATION ?? null,
                "tgllahir_pasangan" => $cr_spouse->BIRTHDATE ?? null,
                "alamat_pasangan" => $cr_spouse->ADDRESS ?? null
            ],
            "info_bank" =>[],
            "ekstra" =>[
                'jenis_angsuran' => empty($application->INSTALLMENT_TYPE)?$cr_survey->jenis_angsuran:$application->INSTALLMENT_TYPE,
                'tenor' => empty($application->TENOR)?strval($cr_survey->tenor):strval($application->TENOR),
                "nilai_yang_diterima" => $applicationDetail->SUBMISSION_VALUE == ''?(int) $data->plafond:(int)$applicationDetail->SUBMISSION_VALUE?? null,
                "periode" => $applicationDetail->PERIOD == ''?$data->tenor:$applicationDetail->PERIOD?? null,
                "total"=> (int)$applicationDetail->TOTAL_ADMIN?? null,
                "cadangan"=> $applicationDetail->CADANGAN?? null,
                "opt_periode"=> $applicationDetail->OPT_PERIODE?? null,
                "provisi"=> $applicationDetail->PROVISION?? null,
                "asuransi"=> $applicationDetail->INSURANCE?? null,
                "biaya_transfer"=> $applicationDetail->TRANSFER_FEE?? null,
                "eff_rate"=> $applicationDetail->EFF_RATE?? null,
                "angsuran"=> intval($applicationDetail->INSTALLMENT)?? null
            ],
            "jaminan_kendaraan" => [],        
            "prospect_approval" => [
                "status" => $approval_detail->application_result == null ?$approval_detail->application_result:""
            ],
            "attachment" =>$attachment_data,
            "approval" => 
            [
                'status' => $approval->application_result??null,
                'kapos' => $approval->cr_application_kapos_desc??null,
                'ho' => $approval->cr_application_ho_desc ??null           
            ]
        ];
        
        $arrayList['info_bank'] = M_CrApplicationBank::where('APPLICATION_ID', $application->ID)
                                ->get()
                                ->map(function ($list) {
                                    return [
                                        "kode_bank" => $list->BANK_CODE,
                                        "nama_bank" => $list->BANK_NAME,
                                        "no_rekening" => $list->ACCOUNT_NUMBER,
                                        "atas_nama" => $list->ACCOUNT_NAME,
                                        "status" => $list->STATUS
                                    ];
                                })
                                ->all();

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan_kendaraan'] = [
                'id' => $list->ID,
                "tipe" => $list->TYPE,
                "merk" => $list->BRAND,
                "tahun" => $list->PRODUCTION_YEAR,
                "warna" => $list->COLOR,
                "atas_nama" => $list->ON_BEHALF,
                "no_polisi" => $list->POLICE_NUMBER,
                "no_rangka" => $list->CHASIS_NUMBER,
                "no_mesin" => $list->ENGINE_NUMBER,
                "no_stnk" => $list->STNK_NUMBER,
                "nilai" =>intval($list->VALUE),
                "no_bpkb" => $list->BPKB_NUMBER,
            ];    
        }  
        
        return $arrayList;
    }

    private function insert_taksasi($request,$id){

        $check = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$id)->first();

        $data_order =[
            'TYPE' => $request->barang_taksasi['tipe']??null,
            'BRAND' => $request->barang_taksasi['merk']??null,
            'PRODUCTION_YEAR' => $request->barang_taksasi['tahun']??null,
            'COLOR' => $request->barang_taksasi['warna']??null,
            'ON_BEHALF' => $request->barang_taksasi['atas_nama']??null,
            'POLICE_NUMBER' => $request->barang_taksasi['no_polisi']??null,
            'CHASIS_NUMBER' => $request->barang_taksasi['no_rangka']??null,
            'ENGINE_NUMBER' => $request->barang_taksasi['no_mesin']??null,
            'STNK_NUMBER' => $request->barang_taksasi['no_stnk']??null,
            'VALUE' => $request->barang_taksasi['nilai']??null,
            'BPKB_NUMBER' => $request->barang_taksasi['no_bpkb']??null
        ];

        $check->update($data_order);
    }


    public function approvalKapos(Request $request)
    {
        try {
            $request->validate([
                'cr_application_id' => 'required|string',
                'flag' => 'required|string',
            ]);
        
            $check_application_id = M_ApplicationApproval::where('cr_application_id', $request->cr_application_id)->first();
        
            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }
        
            $checkApplication = M_CrApplication::where('ID', $request->cr_application_id)->first();
            $approval_change = M_SurveyApproval::where('CR_SURVEY_ID', $checkApplication->CR_SURVEY_ID)->first();
        
            $data_approval = [
                'cr_application_kapos' => $request->user()->id,
                'cr_application_kapos_time' => Carbon::now()->format('Y-m-d'),
                'cr_application_kapos_desc' => $request->keterangan,
            ];
        
            if ($request->flag === 'yes') {
                $data_approval['cr_application_kapos_note'] = $request->flag;
                $data_approval['application_result'] = '2:waiting ho';
        
                $change_approval = [
                    'APPROVAL_RESULT' => '4:waiting ho'
                ];
        
                $approvalLogMessage = '4:waiting ho';
            } else {
                $data_approval['cr_application_kapos_note'] = $request->flag;
                $data_approval['application_result'] = '6:closed kapos';
        
                $change_approval = [
                    'APPROVAL_RESULT' => '5:closed kapos'
                ];
        
                $approvalLogMessage = '5:closed kapos';
            }
        
            $approval_change->update($change_approval);
            $approvalLog = new ApprovalLog();
            $approvalLog->surveyApprovalLog($request->user()->id, $approval_change->ID, $approvalLogMessage);
        
            $check_application_id->update($data_approval);
        
            return response()->json(['message' => 'Approval Kapos Successfully', "status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function approvalHo(Request $request)
    {
        try {
            $request->validate([
                'cr_application_id' => 'required|string',
                'flag' => 'required|string',
            ]);
        
            $check_application_id = M_ApplicationApproval::where('cr_application_id', $request->cr_application_id)->first();
        
            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }
        
            $checkApplication = M_CrApplication::where('ID', $request->cr_application_id)->first();
            $approval_change = M_SurveyApproval::where('CR_SURVEY_ID', $checkApplication->CR_SURVEY_ID)->first();
        
            $data_approval = [
                'cr_application_ho' => $request->user()->id,
                'cr_application_ho_time' => Carbon::now()->format('Y-m-d'),
                'cr_application_ho_desc' => $request->keterangan,
            ];
        
            if ($request->flag === 'yes') {
                $data_approval['cr_application_ho_note'] = $request->flag;
                $data_approval['application_result'] = '3:approved ho';
        
                $change_approval = [
                    'APPROVAL_RESULT' => '6:approved ho'
                ];
        
                $approvalLogMessage = '6:approved ho';
            }elseif ($request->flag === 'no') {
                $data_approval['cr_application_ho_note'] = $request->flag;
                $data_approval['application_result'] = '4:reject ho';
        
                $change_approval = [
                    'APPROVAL_RESULT' => '7:reject ho'
                ];
        
                $approvalLogMessage = '7:reject ho';
            } else {
                $data_approval['cr_application_ho_note'] = $request->flag;
                $data_approval['application_result'] = '5:closed ho';
        
                $change_approval = [
                    'APPROVAL_RESULT' => '8:closed ho'
                ];
        
                $approvalLogMessage = '8:closed ho';
            }
        
            $approval_change->update($change_approval);
            $approvalLog = new ApprovalLog();
            $approvalLog->surveyApprovalLog($request->user()->id, $approval_change->ID, $approvalLogMessage);
        
            $check_application_id->update($data_approval);
        
            return response()->json(['message' => 'Approval Kapos Successfully', "status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_ApplicationApproval;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationBank;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrProspect;
use App\Models\M_CrProspectDocument;
use App\Models\M_HrEmployee;
use App\Models\M_ProspectApproval;
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
            $data = M_CrApplication::fpkListData();
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function showKapos(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData('0:draft');
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $request,$id)
    {
        try {
            $check = M_CrApplication::where('CR_PROSPECT_ID',$id)->whereNull('deleted_at')->first();

            if (!$check) {
                $check_application_id = M_CrApplication::where('ID',$id)->whereNull('deleted_at')->first();
            }else {
                $check_application_id = $check;
            }

            if (!isset($check_application_id->CR_PROSPECT_ID)  || $check_application_id->CR_PROSPECT_ID == '') {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $detail_prospect = M_CrProspect::where('id',$check_application_id->CR_PROSPECT_ID)->first();

            return response()->json(['message' => 'OK',"status" => 200,'response' => self::resourceDetail($detail_prospect,$check_application_id)], 200);
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

            $check_prospect_id = M_CrProspect::where('id',$request->data_order['cr_prospect_id'])
                                                ->whereNull('deleted_at')->first();

            if (!$check_prospect_id) {
                throw new Exception("Id Kunjungan Is Not Exist", 404);
            }

            self::insert_cr_application($request,$uuid);
            // self::update_cr_prospect($request,$check_prospect_id);
            self::insert_cr_personal($request,$uuid);
            self::insert_cr_personal_extra($request,$uuid);
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

            $check_application_id = M_CrApplication::where('ID',$id)->first();

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $check_order_number = M_CrApplication::where('ID',$id)->where('ORDER_NUMBER','')->orWhere('ORDER_NUMBER',null)->first();

            if($check_order_number){
                $data_application['ORDER_NUMBER'] =createAutoCode(M_CrApplication::class,'ORDER_NUMBER','FPK');
            }

            $check_application_id->update($data_application); 

            self::insert_cr_personal($request,$id);

            $check_survey_id = M_CrProspect::where('id',$check_application_id->CR_PROSPECT_ID)->first();

            if (!$check_survey_id) {
                throw new Exception("Id Survey Is Not Exist", 404);
            }else{
                $data_prospect =[
                    'mother_name' =>$request->data_order['nama_ibu'],
                    'category' =>$request->data_order['kategori'],
                    'title' =>$request->data_order['gelar'],
                    'work_period'  =>$request->data_order['lama_bekerja'],
                    'dependants'  =>$request->data_order['tanggungan'],
                    'income_personal'  =>$request->data_order['pendapatan_pribadi'],
                    'income_spouse'  =>$request->data_order['pendapatan_pasangan'],
                    'income_other'  =>$request->data_order['pendapatan_lainnya'],
                    'expenses'  =>$request->data_order['biaya_bulanan']
                ];

                $check_survey_id->update($data_prospect);
            }

            if (collect($request->data_tambahan)->isNotEmpty()) {
                self::insert_cr_personal_extra($request,$id);
            }

            if (collect($request->bank)->isNotEmpty()) {
                self::insert_bank_account($request,$id);
            }

            self::insert_application_approval($id,$request->flag_pengajuan);

            return response()->json(['message' => 'Updated Successfully',"status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function insert_cr_application($request,$uuid){
        $data_cr_application =[
            'ID' => $uuid,
            'BRANCH' => M_HrEmployee::findEmployee($request->user()->employee_id)->BRANCH_ID,
            'FORM_NUMBER' => '',
            'ORDER_NUMBER' => '',
            'CUST_CODE' => '',
            'ENTRY_DATE' => Carbon::now()->format('Y-m-d'),
            'SUBMISSION_VALUE' => 0.00,
            'CREDIT_TYPE' => '',
            'INSTALLMENT_COUNT' => 0.00,
            'PERIOD' => 0,
            'INSTALLMENT' => 0.00,
            'RATE' => 0.00,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        M_CrApplication::create($data_cr_application);
    }

    private function insert_cr_personal($request,$applicationId){

        $check = M_CrPersonal::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'CUST_CODE' => $request->pelanggan['code']??null,
            'NAME' => $request->pelanggan['nama'],
            'ALIAS' => $request->pelanggan['nama_panggilan'],
            'GENDER' => $request->pelanggan['jenis_kelamin'],
            'BIRTHPLACE' => $request->pelanggan['tempat_lahir'],
            'BIRTHDATE' => $request->pelanggan['tgl_lahir'],
            'BLOOD_TYPE' => $request->pelanggan['gol_darah'],
            'MARTIAL_STATUS' => $request->pelanggan['status_kawin'],
            'MARTIAL_DATE' => $request->pelanggan['tgl_kawin'],
            'ID_TYPE' => $request->pelanggan['tipe_identitas'],
            'ID_NUMBER' => $request->pelanggan['no_identitas'],
            'ID_ISSUE_DATE' => $request->pelanggan['tgl_terbit'],
            'ID_VALID_DATE' => $request->pelanggan['masa_berlaku'],
            'KK' => $request->pelanggan['no_kk'],
            'CITIZEN' => $request->pelanggan['warganegara'],
            
            'ADDRESS' => $request->alamat_identitas['alamat'],
            'RT' => $request->alamat_identitas['rt'],
            'RW' => $request->alamat_identitas['rw'],
            'PROVINCE' => $request->alamat_identitas['provinsi'],
            'CITY' => $request->alamat_identitas['kota'],
            'KELURAHAN' => $request->alamat_identitas['kelurahan'],
            'KECAMATAN' => $request->alamat_identitas['kecamatan'],
            'ZIP_CODE' =>  $request->alamat_identitas['kode_pos'],

            'INS_ADDRESS' => $request->alamat_tagih['alamat'],
            'INS_RT' => $request->alamat_tagih['rt'],
            'INS_RW' => $request->alamat_tagih['rw'],
            'INS_PROVINCE' => $request->alamat_tagih['provinsi'],
            'INS_CITY' => $request->alamat_tagih['kota'],
            'INS_KELURAHAN' => $request->alamat_tagih['kelurahan'],
            'INS_KECAMATAN' => $request->alamat_tagih['kecamatan'],
            'INS_ZIP_CODE' => $request->alamat_tagih['kode_pos'],

            'OCCUPATION' => $request->pekerjaan['pekerjaan'],
            'OCCUPATION_ON_ID' => $request->pekerjaan['pekerjaan_id'],
            'RELIGION' => $request->pekerjaan['agama'],
            'EDUCATION' => $request->pekerjaan['pendidikan'],
            'PROPERTY_STATUS' => $request->pekerjaan['status_rumah'],
            'PHONE_HOUSE' => $request->pekerjaan['telepon_rumah'],
            'PHONE_PERSONAL' => $request->pekerjaan['telepon_selular'],
            'PHONE_OFFICE' => $request->pekerjaan['telepon_kantor'],
            'EXT_1' => $request->pekerjaan['ekstra1'],
            'EXT_2' => $request->pekerjaan['ekstra2'],
           
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        if(!$check){
            $data_cr_application['ID'] = Uuid::uuid4()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;

            M_CrPersonal::create($data_cr_application);
        }else{
            $check->update($data_cr_application);
        }

         
    }

    private function insert_cr_personal_extra($request,$applicationId){

        $check = M_CrPersonalExtra::where('APPLICATION_ID',$applicationId)->first();

        $data_cr_application =[  
            'BI_NAME' => $request->data_tambahan['nama_bi'],
            'EMAIL' => $request->data_tambahan['email'],
            'INFO' => $request->data_tambahan['info_khusus'],
            'OTHER_OCCUPATION_1' => $request->data_tambahan['usaha_lain_1'],
            'OTHER_OCCUPATION_2' => $request->data_tambahan['usaha_lain_2'],
            'OTHER_OCCUPATION_3' => $request->data_tambahan['usaha_lain_3'],
            'OTHER_OCCUPATION_4' => $request->data_tambahan['usaha_lain_4'],
            'MAIL_ADDRESS' => $request->data_tambahan['surat_alamat'],
            'MAIL_RT' => $request->data_tambahan['surat_rt'],
            'MAIL_RW' => $request->data_tambahan['surat_rw'],
            'MAIL_PROVINCE' => $request->data_tambahan['surat_provinsi'],
            'MAIL_CITY' => $request->data_tambahan['surat_kota'],
            'MAIL_KELURAHAN' => $request->data_tambahan['surat_kelurahan'],
            'MAIL_KECAMATAN' => $request->data_tambahan['surat_kecamatan'],
            'MAIL_ZIP_CODE' => $request->data_tambahan['surat_kode_pos'],
            'EMERGENCY_NAME' => $request->data_tambahan['nama_kerabat_darurat'],
            'EMERGENCY_ADDRESS' => $request->data_tambahan['alamat_kerabat_darurat'],
            'EMERGENCY_RT' => $request->data_tambahan['rt_kerabat_darurat'],
            'EMERGENCY_RW' => $request->data_tambahan['rw_kerabat_darurat'],
            'EMERGENCY_PROVINCE' => $request->data_tambahan['provinsi_kerabat_darurat'],
            'EMERGENCY_CITY' => $request->data_tambahan['kota_kerabat_darurat'],
            'EMERGENCY_KELURAHAN' => $request->data_tambahan['kelurahan_kerabat_darurat'],
            'EMERGENCY_KECAMATAN' => $request->data_tambahan['kecamatan_kerabat_darurat'],
            'EMERGENCY_ZIP_CODE' => $request->data_tambahan['kode_pos_kerabat_darurat'],
            'EMERGENCY_PHONE_HOUSE' => $request->data_tambahan['no_telp_kerabat_darurat'],
            'EMERGENCY_PHONE_PERSONAL'  => $request->data_tambahan['no_hp_kerabat_darurat'] 
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

        $check = M_CrApplicationBank::where('APPLICATION_ID',$applicationId)->get();

        if ($check->isNotEmpty()) {
            M_CrApplicationBank::where('APPLICATION_ID', $applicationId)->delete();
        }

        if (isset($request->bank) && is_array($request->bank)) {
            foreach ($request->bank as $result) {
                $data_cr_application_bank =[  
                    'ID' => Uuid::uuid4()->toString(),
                    'APPLICATION_ID' => $applicationId,
                    'BANK_CODE' => $result['kode_bank'],
                    'BANK_NAME' => $result['nama_bank'],
                    'ACCOUNT_NUMBER' => $result['no_rekening'],
                    'ACCOUNT_NAME' => $result['nama_di_rekening'],
                    'PREFERENCE_FLAG' => '',
                    'STATUS' => $result['status']   
                ];

                M_CrApplicationBank::create($data_cr_application_bank);
            }
        }
    }

    private function insert_application_approval($applicationId,$flag){

        $data_approval =[  
            'ID' => Uuid::uuid4()->toString(),
            'cr_application_id' => $applicationId
        ];

        if($flag === 'yes'){
            $data_approval['application_result'] = '1:waiting kapos';
        }else{
            $data_approval['application_result'] = '0:draft';
        }

        M_ApplicationApproval::create($data_approval);
    }

    public function generateUuidFPK(Request $request)
    {
        try {
            $check_prospect_id = M_CrProspect::where('id',$request->cr_prospect_id)
                                ->whereNull('deleted_at')->first();

            if (!$check_prospect_id) {
                throw new Exception("Id Kunjungan Is Not Exist", 404);
            }

            $uuid = Uuid::uuid4()->toString();

            $check_prospect_id = M_CrApplication::where('CR_PROSPECT_ID',$request->cr_prospect_id)->first();

            if(!$check_prospect_id){
                $generate_uuid = $uuid;

                $data_cr_application =[
                    'ID' => $uuid,
                    'CR_PROSPECT_ID' => $request->cr_prospect_id,
                    'BRANCH' => M_HrEmployee::findEmployee($request->user()->employee_id)->BRANCH_ID,
                    'VERSION' => 1,
                    'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                    'CREATE_USER' => $request->user()->id,
                ];
        
                M_CrApplication::create($data_cr_application);
            }else{
                $generate_uuid = $check_prospect_id->ID;
            }

            $approval_change = M_ProspectApproval::where('CR_PROSPECT_ID',$request->cr_prospect_id)->first();

            $data_update_approval=[
                'APPROVAL_RESULT' => '2:created_fpk'
            ];

            $approval_change->update($data_update_approval);

            return response()->json(['message' => 'OK',"status" => 200,'response' => ['uuid'=>$generate_uuid]], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function resourceDetail($data,$application)
    {
        $prospect_id = $data->id;
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_PROSPECT_ID',$prospect_id)->get(); 
        $approval_detail = M_ProspectApproval::where('CR_PROSPECT_ID',$prospect_id)->first();
        $attachment_data = M_CrProspectDocument::where('CR_PROSPECT_ID',$prospect_id )->get();
        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$application->ID)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID',$application->ID)->first();
        $cr_application_bank = M_CrApplicationBank::where('APPLICATION_ID',$application->ID)->first();
        
        $arrayList = [
            'id_application' => $application->ID,
            'pelanggan' =>[
                "nama" => $data->nama,
                "nama_panggilan" => $cr_personal->ALIAS ?? null,
                "jenis_kelamin" => $cr_personal->GENDER ?? null,
                "tempat_lahir" => $cr_personal->BIRTHPLACE ?? null,
                "tgl_lahir" => checkDateIfNull($data->tgl_lahir),
                "gol_darah" => $cr_personal->BLOOD_TYPE??null,
                "status_kawin" => $cr_personal->MARTIAL_STATUS??null,
                "tgl_kawin" => $cr_personal->MARTIAL_DATE ?? null,
                "tipe_identitas" => $cr_personal->ID_TYPE??null,
                "no_identitas" => $data->ktp,
                "tgl_terbit_identitas" => $cr_personal->ID_ISSUE_DATE ??null,
                "masa_berlaku_identitas" => $cr_personal->ID_VALID_DATE ?? null,
                "no_kk" => $cr_personal->KK??null,
                "warganegara" => $cr_personal->CITIZEN??null
            ],
            'alamat_identitas' =>[
                "alamat" => $data->alamat,
                "rt" => $data->rt,
                "rw" => $data->rw,
                "provinsi" => $data->province,
                "kota" => $data->city,
                "kelurahan" => $data->kelurahan,
                "kecamatan" => $data->kecamatan,
                "kode_pos" => $data->zip_code
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
            'pekerjaan' =>[
                "pekerjaan" => $cr_personal->OCCUPATION??null,
                "pekerjaan_id" => $cr_personal->OCCUPATION_ON_ID??null,
                "agama" => $cr_personal->RELIGION??null,
                "pendidikan" => $cr_personal->EDUCATION??null,
                "status_rumah" => $cr_personal->PROPERTY_STATUS??null,
                "telepon_rumah" => $cr_personal->PHONE_HOUSE??null,
                "telepon_selular" => $data->hp,
                "telepon_kantor" => $cr_personal->PHONE_OFFICE??null,
                "ekstra1" => $cr_personal->EXT_1??null,
                "ekstra2" => $cr_personal->EXT_2??null
            ],
            'order' =>[
                'cr_prospect_id' => $prospect_id,
                "nama_ibu" => $data->work_period, 
                "kategori" => $data->category, 
                "gelar" => $data->title, 
                "lama_bekerja" => $data->mother_name, 
                "tanggungan" => $data->dependants, 
                "biaya_bulanan" => $data->expense, 
                "pendapatan_pribadi" => $data->income_personal,
                "pendapatan_pasangan" => $data->income_spouse,
                "pendapatan_lainnya" => $data->income_other,
                "order_tanggal" => "",
                "order_status" => "",
                "order_tipe" => "",
                "unit_bisnis" => "", 
                "cust_service" => "",
                "ref_pelanggan" => $data->cust_code_ref,
                "surveyor_id" => "",
                "catatan_survey" => $data->survey_note,
                "prog_marketing" => "",
                "cara_bayar" => ""
            ],
            'tambahan' =>[
                "nama_bi"  => $cr_personal_extra->BI_NAME ?? null, 
                "email"  => $cr_personal_extra->EMAIL?? null,
                "info_khusus"  => $cr_personal_extra->INFO?? null,
                "usaha_lain_1"  => $cr_personal_extra->OTHER_OCCUPATION_1?? null,
                "usaha_lain_2"  => $cr_personal_extra->OTHER_OCCUPATION_2?? null,
                "usaha_lain_3"  => $cr_personal_extra->OTHER_OCCUPATION_3?? null,
                "usaha_lain_4"  => $cr_personal_extra->OTHER_OCCUPATION_4?? null,
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
            "surat" => [
                "alamat"  => $cr_personal_extra->MAIL_ADDRESS?? null,
                "rt"  => $cr_personal_extra->MAIL_RT?? null,
                "rw"  => $cr_personal_extra->MAIL_RW?? null,
                "provinsi" => $cr_personal_extra->MAIL_PROVINCE?? null,
                "kota" => $cr_personal_extra->MAIL_CITY?? null,
                "kelurahan" => $cr_personal_extra->MAIL_KELURAHAN?? null,
                "kecamatan" => $cr_personal_extra->MAIL_KECAMATAN?? null,
                "kode_pos" => $cr_personal_extra->MAIL_ZIP_CODE?? null    
            ],
            "info_bank" =>[],
            "ekstra" =>[
                "nilai_yang_diterima" => $data->plafond,
                "periode" => $data->tenor,
                "pokok_pembayaran"=> "",
                "tipe_angsuran"=> "",
                "cara_pembayaran"=> "",
                "angsuran"=> "",
                "provisi"=> "",
                "asuransi"=> "",
                "biaya_transfer"=> "",
                "bunga_margin_eff"=> "",
                "bunga_margin_flat"=> "",
                "bunga_margin"=>"",
                "pokok_margin"=>"",
                "angsuran_terakhir"=>"",
                "bunga_margin_eff_actual"=>"",
                "bunga_margin_eff_flat"=>"",
                "nett_admin"=>"",
            ],
            "jaminan_kendaraan" => [],        
            "prospect_approval" => [
                "flag_approval" => $approval_detail->ONCHARGE_APPRVL,
                "keterangan" => $approval_detail->ONCHARGE_DESCR,
                "status" => $approval_detail->APPROVAL_RESULT
            ],
            "attachment" =>$attachment_data
        ];
        
        if(!empty($cr_application_bank) && is_array($cr_application_bank)){
            foreach ($cr_application_bank as $list) {
                $arrayList['info_bank'][] = [
                    "kode_bank" => $list['BANK_CODE'] ?? null,
                    "nama_bank" => $list['BANK_NAME'] ?? null,
                    "no_rekening" => $list['ACCOUNT_NUMBER'] ?? null,
                    "nama_di_rekening" => $list['ACCOUNT_NAME'] ?? null,
                    "status" => $list['STATUS'] ?? null
                ];    
            }
        }

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan_kendaraan'][] = [
                'id' => $list->ID,
                "tipe" => $list->TYPE,
                "merk" => $list->BRAND,
                "tahun" => $list->PRODUCTION_YEAR,
                "warna" => $list->COLOR,
                "atas_nama" => $list->ON_BEHALF,
                "no_polisi" => $list->POLICE_NUMBER,
                "no_rangka" => $list->CHASIS_NUMBER,
                "no_mesin" => $list->ENGINE_NUMBER,
                "no_stnk" => $list->BPKB_NUMBER,
                "nilai" =>$list->VALUE
            ];    
        }  
        
        return $arrayList;
    }

    public function approvalKapos(Request $request)
    {
        try {
            $request->validate([
                'cr_application_id' => 'required|string',
                'flag' => 'required|string',
            ]);

            $check_application_id = M_ApplicationApproval::where('cr_application_id',$request->cr_application_id)->first();

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $data_approval=[
                'ID' => Uuid::uuid4()->toString(),
                'cr_application_kapos' => $request->user()->id,
                'cr_application_kapos_time' => Carbon::now()->format('Y-m-d'),
                'cr_application_kapos_desc' => $request->keterangan
            ];

            if($request->flag === 'yes'){
                $data_approval['cr_application_kapos_note'] = $request->flag;
                $data_approval['application_result'] = '2:waiting ho';
            }else{
                $data_approval['cr_application_kapos_note'] = $request->flag;
                $data_approval['application_result'] = '6:closed kapos';
            }
    
            $check_application_id->update($data_approval);

            return response()->json(['message' => 'Approval Kapos Successfully',"status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    } 
}

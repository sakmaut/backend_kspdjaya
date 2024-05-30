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
            $data = "";
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            if(!empty($request->cr_prospect_id)){
                $check_application_id = M_CrApplication::where('CR_PROSPECT_ID',$request->cr_prospect_id)->whereNull('deleted_at')->first();

                if (!$check_application_id) {
                    throw new Exception("Id Prospeck Is Not Exist", 404);
                }

            }elseif(!empty($request->cr_application_id)) {
                $check_application_id = M_CrApplication::where('ID',$request->cr_application_id)->whereNull('deleted_at')->first();

                if (!$check_application_id) {
                    throw new Exception("Id FPK Is Not Exist", 404);
                }
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
            self::update_cr_prospect($request,$check_prospect_id);
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

    private function update_cr_prospect($request,$check_prospect_id){
        $data_prospect =[
            'mother_name' =>$request->data_order['nama_ibu'],
            'category' =>$request->data_order['kategori'],
            'title' =>$request->data_order['gelar'],
            'work_period'  =>$request->data_order['lama_bekerja'],
            'dependants'  =>$request->data_order['tanggungan'],
            'income_personal'  =>$request->data_order['pendapatan_pribadi'],
            'income_spouse'  =>$request->data_order['pendapatan_pasangan'],
            'income_other'  =>$request->data_order['pendapatan_lainnya'],
            'expenses'  =>$request->data_order['biaya_bulanan'],
            'updated_by' => $request->user()->id,
            'updated_at' => Carbon::now()->format('Y-m-d')
        ];

       $check_prospect_id->update($data_prospect);
    }

    private function insert_cr_personal($request,$applicationId){
        $data_cr_application =[  
            'ID' => Uuid::uuid4()->toString(),
            'APPLICATION_ID' => $applicationId,
            'CUST_CODE' => $request->data_pelanggan['code'],
            'NAME' => $request->data_pelanggan['nama'],
            'ALIAS' => $request->data_pelanggan['nama_panggilan'],
            'GENDER' => $request->data_pelanggan['jenis_kelamin'],
            'BIRTHPLACE' => $request->data_pelanggan['tempat_lahir'],
            'BIRTHDATE' => $request->data_pelanggan['tanggal_lahir'],
            'MARTIAL_STATUS' => $request->data_pelanggan['status_kawin'],
            'MARTIAL_DATE' => $request->data_pelanggan['tanggal_kawin'],
            'ID_TYPE' => $request->data_pelanggan['tipe'],
            'ID_NUMBER' => $request->data_pelanggan['no'],
            'ID_ISSUE_DATE' => $request->data_pelanggan['tgl_terbit'],
            'ID_VALID_DATE' => $request->data_pelanggan['masa_berlaku'],
            'ADDRESS' => $request->data_pelanggan['alamat'],
            'RT' => $request->data_pelanggan['rt'],
            'RW' => $request->data_pelanggan['rw'],
            'PROVINCE' => $request->data_pelanggan['provinsi'],
            'CITY' => $request->data_pelanggan['kota'],
            'KELURAHAN' => $request->data_pelanggan['kelurahan'],
            'KECAMATAN' => $request->data_pelanggan['kecamatan'],
            'ZIP_CODE' =>  $request->data_pelanggan['kode_pos'],
            'KK' => $request->data_pelanggan['no_kk'],
            'CITIZEN' => $request->data_pelanggan['warganegara'],
            'OCCUPATION' => $request->data_pelanggan['pekerjaan'],
            'OCCUPATION_ON_ID' => $request->data_pelanggan['id_pekerjaan'],
            'RELIGION' => $request->data_pelanggan['agama'],
            'EDUCATION' => $request->data_pelanggan['pendidikan'],
            'PROPERTY_STATUS' => $request->data_pelanggan['status_rumah'],
            'PHONE_HOUSE' => $request->data_pelanggan['telepon_rumah'],
            'PHONE_PERSONAL' => $request->data_pelanggan['telepon_selular'],
            'PHONE_OFFICE' => $request->data_pelanggan['telepon_kantor'],
            'EXT_1' => $request->data_pelanggan['ext1'],
            'EXT_2' => $request->data_pelanggan['ext2'],
            'INS_ADDRESS' => $request->data_pelanggan['alamat_tagih_alamat'],
            'INS_RT' => $request->data_pelanggan['alamat_tagih_rt'],
            'INS_RW' => $request->data_pelanggan['alamat_tagih_rw'],
            'INS_PROVINCE' => $request->data_pelanggan['alamat_tagih_provinsi'],
            'INS_CITY' => $request->data_pelanggan['alamat_tagih_kota'],
            'INS_KELURAHAN' => $request->data_pelanggan['alamat_tagih_kelurahan'],
            'INS_KECAMATAN' => $request->data_pelanggan['alamat_tagih_kecamatan'],
            'INS_ZIP_CODE' => $request->data_pelanggan['alamat_tagih_kode_pos'],
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

         M_CrPersonal::create($data_cr_application);
    }

    private function insert_cr_personal_extra($request,$applicationId){
        $data_cr_application =[  
            'ID' => Uuid::uuid4()->toString(),
            'APPLICATION_ID' => $applicationId,
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

         M_CrPersonalExtra::create($data_cr_application);
    }

    private function insert_bank_account($request,$applicationId){
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

    private function insert_application_approval($applicationId){
        $data_approval =[  
            'ID' => Uuid::uuid4()->toString(),
            'cr_application_id' => $applicationId ,
            'application_result' => "0:untouched"
        ];

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
            'data_pelanggan' =>[
                "code" => $cr_personal->CUST_CODE??null,
                "nama" => $data->nama,
                "nama_panggilan" => $cr_personal->ALIAS??null,
                "jenis_kelamin" => $cr_personal->GENDER??null,
                "tempat_lahir" => $cr_personal->BIRTHPLACE??null,
                "tanggal_lahir" => $data->tgl_lahir == null ? null:date('Y-m-d',strtotime($data->tgl_lahir)),
                "status_kawin" => $cr_personal->MARTIAL_STATUS??null,
                "tanggal_kawin" => $cr_personal->MARTIAL_DATE??null,
                "tipe" => $cr_personal->ID_TYPE??null,
                "no" => $data->ktp,
                "tgl_terbit" => $cr_personal->ID_ISSUE_DATE??null,
                "masa_berlaku" => $cr_personal->ID_VALID_DATE??null,
                "alamat" => $data->alamat,
                "rt" => $data->rt,
                "rw" => $data->rw,
                "provinsi" => $data->province,
                "kota" => $data->city,
                "kelurahan" => $data->kelurahan,
                "kecamatan" => $data->kecamatan,
                "kode_pos" => $data->zip_code,
                "no_kk" => $cr_personal->KK??null,
                "warganegara" => $cr_personal->CITIZEN??null,
                "pekerjaan" => $cr_personal->OCCUPATION??null,
                "id_pekerjaan" => $cr_personal->OCCUPATION_ON_ID??null,
                "agama" => $cr_personal->RELIGION??null,
                "pendidikan" => $cr_personal->EDUCATION??null,
                "status_rumah" => $cr_personal->PROPERTY_STATUS??null,
                "telepon_rumah" => $cr_personal->PHONE_HOUSE??null,
                "telepon_selular" => $data->hp,
                "telepon_kantor" => $cr_personal->PHONE_OFFICE??null,
                "ext1" => $cr_personal->EXT_1??null,
                "ext2" => $cr_personal->EXT_2??null,
                "alamat_tagih_alamat" => $cr_personal->INS_ADDRESS??null,
                "alamat_tagih_rt" => $cr_personal->INS_RT??null,
                "alamat_tagih_rw" => $cr_personal->INS_RW??null,
                "alamat_tagih_provinsi" => $cr_personal->INS_PROVINCE??null,
                "alamat_tagih_kota" => $cr_personal->INS_CITY??null,
                "alamat_tagih_kelurahan" => $cr_personal->INS_KELURAHAN??null,
                "alamat_tagih_kecamatan" => $cr_personal->INS_KECAMATAN??null,
                "alamat_tagih_kode_pos" => $cr_personal->INS_ZIP_CODE??null
            ],
            'data_order' =>[
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
            "data_pelanggan_tambahan" => [
                "nama_bi"  => $cr_personal_extra->BI_NAME ?? null, 
                "email"  => $cr_personal_extra->EMAIL?? null,
                "info_khusus"  => $cr_personal_extra->INFO?? null,
                "usaha_lain_1"  => $cr_personal_extra->OTHER_OCCUPATION_1?? null,
                "usaha_lain_2"  => $cr_personal_extra->OTHER_OCCUPATION_2?? null,
                "usaha_lain_3"  => $cr_personal_extra->OTHER_OCCUPATION_3?? null,
                "usaha_lain_4"  => $cr_personal_extra->OTHER_OCCUPATION_4?? null,
                "surat_alamat"  => $cr_personal_extra->MAIL_ADDRESS?? null,
                "surat_rt"  => $cr_personal_extra->MAIL_RT?? null,
                "surat_rw"  => $cr_personal_extra->MAIL_RW?? null,
                "surat_provinsi" => $cr_personal_extra->MAIL_PROVINCE?? null,
                "surat_kota" => $cr_personal_extra->MAIL_CITY?? null,
                "surat_kelurahan" => $cr_personal_extra->MAIL_KELURAHAN?? null,
                "surat_kecamatan" => $cr_personal_extra->MAIL_KECAMATAN?? null,
                "surat_kode_pos" => $cr_personal_extra->MAIL_ZIP_CODE?? null,
                "nama_kerabat_darurat"  => $cr_personal_extra->EMERGENCY_NAME?? null,
                "alamat_kerabat_darurat"  => $cr_personal_extra->EMERGENCY_ADDRESS?? null,
                "rt_kerabat_darurat"  => $cr_personal_extra->EMERGENCY_RT?? null,
                "rw_kerabat_darurat"  => $cr_personal_extra->EMERGENCY_RW?? null,
                "provinsi_kerabat_darurat" =>$cr_personal_extra->EMERGENCY_PROVINCE?? null,
                "kota_kerabat_darurat" => $cr_personal_extra->EMERGENCY_CITY?? null,
                "kelurahan_kerabat_darurat" => $cr_personal_extra->EMERGENCY_KELURAHAN?? null,
                "kecamatan_kerabat_darurat" => $cr_personal_extra->EMERGENCY_KECAMATAN?? null,
                "kode_pos_kerabat_darurat" => $cr_personal_extra->EMERGENCY_ZIP_CODE?? null,
                "no_telp_kerabat_darurat" => $cr_personal_extra->EMERGENCY_PHONE_HOUSE?? null,
                "no_hp_kerabat_darurat" => $cr_personal_extra->EMERGENCY_PHONE_PERSONAL?? null,      
            ],
            "bank" =>[],
            "jaminan_kendaraan" => [],
            "lokasi" => [ 
                "coordinate" => $data->coordinate,
                "accurate" => $data->accurate
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
                $arrayList['bank'][] = [
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

            if ($request->flag_pengajuan == "yes") {
                self::insert_application_approval($id);
            }


            if (collect($request->data_pelanggan)->isNotEmpty()) {
                self::insert_cr_personal($request,$id);
            }

            if (collect($request->data_tambahan)->isNotEmpty()) {
                self::insert_cr_personal_extra($request,$id);
            }

            if (collect($request->bank)->isNotEmpty()) {
                self::insert_bank_account($request,$id);
            }

            return response()->json(['message' => 'Updated Successfully',"status" => 200], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

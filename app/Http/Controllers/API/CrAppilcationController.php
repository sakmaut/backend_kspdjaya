<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_ApplicationApproval;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationBank;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
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

            $check_application_id = M_CrApplication::find($id);

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            self::insert_cr_application($request,$check_application_id);
            self::insert_cr_personal($request,$id);
            self::insert_cr_order($request,$check_application_id->CR_PROSPECT_ID,$id);
            self::insert_cr_personal_extra($request,$id);
            self::insert_bank_account($request,$id);
            self::insert_application_approval($id,$request->flag_pengajuan);

            return response()->json(['message' => 'Updated Successfully',"status" => self::insert_bank_account($request,$id)], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function insert_cr_application($request,$uuid){

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
            'FLAT_RATE' => $request->ekstra['bunga_margin_flat']??null,
            'EFF_RATE' => $request->ekstra['bunga_margin_eff']??null,
            'PAYMENT_WAY'=> $request->ekstra['cara_pembayaran']??null,
            'PROVISION'=> $request->ekstra['provisi']??null,
            'INSURANCE'=> $request->ekstra['asuransi']??null,
            'TRANSFER_FEE'=> $request->ekstra['biaya_transfer']??null,
            'INTEREST_MARGIN'=> $request->ekstra['bunga_margin']??null,
            'PRINCIPAL_MARGIN'=> $request->ekstra['pokok_margin']??null,
            'LAST_INSTALLMENT'=> $request->ekstra['angsuran_terakhir']??null,
            'INTEREST_MARGIN_EFF_ACTUAL'=> $request->ekstra['bunga_margin_eff_actual']??null,
            'INTEREST_MARGIN_EFF_FLAT'=> $request->ekstra['bunga_margin_eff_flat']??null,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        if(!$uuid){
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['BRANCH'] = M_HrEmployee::findEmployee($request->user()->employee_id)->BRANCH_ID;
            $data_cr_application['ORDER_NUMBER'] = createAutoCode(M_CrApplication::class,'ORDER_NUMBER','FPK');
            M_CrApplication::create($data_cr_application);
        }else{
            $uuid->update($data_cr_application);
        } 
    }

    private function insert_cr_order($request,$id,$fpkId){
        $check_survey_id = M_CrProspect::where('id',$id)->first();

        if (!$check_survey_id) {
            throw new Exception("Id Survey Is Not Exist", 404);
        }

        $data_prospect =[
            'mother_name' =>$request->order['nama_ibu']??null,
            'category' =>$request->order['kategori']??null,
            'title' =>$request->order['gelar']??null,
            'work_period'  =>$request->order['lama_bekerja']??null,
            'dependants'  =>$request->order['tanggungan']??null,
            'income_personal'  =>$request->order['pendapatan_pribadi']??null,
            'income_spouse'  =>$request->order['pendapatan_pasangan']??null,
            'income_other'  =>$request->order['pendapatan_lainnya']??null,
            'expenses'  =>$request->order['biaya_bulanan']??null
        ];

        $check_survey_id->update($data_prospect);

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
            'HARGA_PASAR' => $request->barang_taksasi['harga_pasar']??null
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
            'CUST_CODE' => $request->pelanggan['code']??null,
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

            M_CrPersonal::create($data_cr_application);
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

        M_CrApplicationBank::where('APPLICATION_ID',$applicationId)->get();

        if (isset($request->info_bank) && is_array($request->info_bank)) {
            
            M_CrApplicationBank::where('APPLICATION_ID', $applicationId)->delete();

            foreach ($request->info_bank as $result) {
                $data_cr_application_bank =[  
                    'ID' => Uuid::uuid4()->toString(),
                    'APPLICATION_ID' => $applicationId,
                    'BANK_CODE' => $result['kode_bank']??null,
                    'BANK_NAME' => $result['nama_bank']??null,
                    'ACCOUNT_NUMBER' => $result['no_rekening']??null,
                    'ACCOUNT_NAME' => $result['atas_nama']??null,
                    'STATUS' => $result['status']??null   
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

            $uuid = Uuid::uuid7()->toString();

            $check_prospect_id = M_CrApplication::where('CR_PROSPECT_ID',$request->cr_prospect_id)->first();

            if(!$check_prospect_id){
                $generate_uuid = $uuid;

                $data_cr_application =[
                    'ID' => $uuid,
                    'CR_PROSPECT_ID' => $request->cr_prospect_id,
                    'ORDER_NUMBER' => createAutoCode(M_CrApplication::class,'ORDER_NUMBER','FPK'),
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
        $cr_oder = M_CrOrder::where('APPLICATION_ID',$application->ID)->first();

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
                "alamat" => $cr_personal->ADDRESS??null,
                "rt" => $cr_personal->RT??null,
                "rw" => $cr_personal->RW??null,
                "provinsi" => $cr_personal->PROVINCE??null,
                "kota" => $cr_personal->CITY??null,
                "kelurahan" => $cr_personal->KELURAHAN??null,
                "kecamatan" => $cr_personal->KECAMATAN??null,
                "kode_pos" => $cr_personal->ZIP_CODE??null
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
                'cr_prospect_id' => $prospect_id??null,
                "nama_ibu" => $data->work_period??null, 
                "kategori" => $data->category??null, 
                "gelar" => $data->title??null, 
                "lama_bekerja" => $data->mother_name??null, 
                "tanggungan" => $data->dependants??null, 
                "biaya_bulanan" => $cr_oder->BIAYA??null, 
                "pendapatan_pribadi" => $data->income_personal??null,
                "pendapatan_pasangan" => $data->income_spouse??null,
                "pendapatan_lainnya" => $data->income_other??null,
                "no_npwp" => $cr_oder->NO_NPWP??null,
                "order_tanggal" =>  $cr_oder->ORDER_TANGGAL??null,
                "order_status" =>  $cr_oder->ORDER_STATUS??null,
                "order_tipe" =>  $cr_oder->ORDER_TIPE??null,
                "unit_bisnis" => $cr_oder->UNIT_BISNIS??null, 
                "cust_service" => $cr_oder->CUST_SERVICE??null,
                "ref_pelanggan" => $cr_oder->REF_PELANGGAN??null,
                "surveyor_id" => null,
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
                // "pokok_pembayaran"=> "",
                // "tipe_angsuran"=> "",
                // "cara_pembayaran"=> "",
                // "angsuran"=> "",
                // "provisi"=> "",
                // "asuransi"=> "",
                // "biaya_transfer"=> "",
                // "bunga_margin_eff"=> "",
                // "bunga_margin_flat"=> "",
                // "bunga_margin"=>"",
                // "pokok_margin"=>"",
                // "angsuran_terakhir"=>"",
                // "bunga_margin_eff_actual"=>"",
                // "bunga_margin_eff_flat"=>"",
                // "nett_admin"=>"",
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
                    "atas_nama" => $list['ACCOUNT_NAME'] ?? null,
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

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationBank;
use App\Models\M_CrPersonal;
use App\Models\M_CrProspect;
use App\Models\M_HrEmployee;
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
            'CUST_CODE' => $request->data_pelanggan['data_pribadi']['code'],
            'NAME' => $request->data_pelanggan['data_pribadi']['nama'],
            'ALIAS' => $request->data_pelanggan['data_pribadi']['nama_panggilan'],
            'GENDER' => $request->data_pelanggan['data_pribadi']['jenis_kelamin'],
            'BIRTHPLACE' => $request->data_pelanggan['data_pribadi']['tempat_lahir'],
            'BIRTHDATE' => $request->data_pelanggan['data_pribadi']['tanggal_lahir'],
            'MARTIAL_STATUS' => $request->data_pelanggan['data_pribadi']['status_kawin'],
            'MARTIAL_DATE' => $request->data_pelanggan['data_pribadi']['tanggal_kawin'],
            'ID_TYPE' => $request->data_pelanggan['data_pribadi']['indentitas']['tipe'],
            'ID_NUMBER' => $request->data_pelanggan['data_pribadi']['indentitas']['no'],
            'ID_ISSUE_DATE' => $request->data_pelanggan['data_pribadi']['indentitas']['tgl_terbit'],
            'ID_VALID_DATE' => $request->data_pelanggan['data_pribadi']['indentitas']['masa_berlaku'],
            'ADDRESS' => $request->data_pelanggan['data_pribadi']['indentitas']['alamat'],
            'RT' => $request->data_pelanggan['data_pribadi']['indentitas']['rt'],
            'RW' => $request->data_pelanggan['data_pribadi']['indentitas']['rw'],
            'PROVINCE' => $request->data_pelanggan['data_pribadi']['indentitas']['provinsi'],
            'CITY' => $request->data_pelanggan['data_pribadi']['indentitas']['kota'],
            'KELURAHAN' => $request->data_pelanggan['data_pribadi']['indentitas']['kelurahan'],
            'KECAMATAN' => $request->data_pelanggan['data_pribadi']['indentitas']['kecamatan'],
            'ZIP_CODE' =>  $request->data_pelanggan['data_pribadi']['indentitas']['kode_pos'],
            'KK' => $request->data_pelanggan['data_pribadi']['no_kk'],
            'CITIZEN' => $request->data_pelanggan['data_pribadi']['warganegara'],
            'OCCUPATION' => $request->data_pelanggan['data_pribadi']['pekerjaan'],
            'OCCUPATION_ON_ID' => $request->data_pelanggan['data_pribadi']['id_pekerjaan'],
            'RELIGION' => $request->data_pelanggan['data_pribadi']['agama'],
            'EDUCATION' => $request->data_pelanggan['data_pribadi']['pendidikan'],
            'PROPERTY_STATUS' => $request->data_pelanggan['data_pribadi']['status_rumah'],
            'PHONE_HOUSE' => $request->data_pelanggan['data_pribadi']['telepon_rumah'],
            'PHONE_PERSONAL' => $request->data_pelanggan['data_pribadi']['telepon_selular'],
            'PHONE_OFFICE' => $request->data_pelanggan['data_pribadi']['telepon_kantor'],
            'EXT_1' => $request->data_pelanggan['data_pribadi']['ext1'],
            'EXT_2' => $request->data_pelanggan['data_pribadi']['ext2'],
            'INS_ADDRESS' => $request->data_pelanggan['alamat_tagih']['alamat'],
            'INS_RT' => $request->data_pelanggan['alamat_tagih']['rt'],
            'INS_RW' => $request->data_pelanggan['alamat_tagih']['rw'],
            'INS_PROVINCE' => $request->data_pelanggan['alamat_tagih']['provinsi'],
            'INS_CITY' => $request->data_pelanggan['alamat_tagih']['kota'],
            'INS_KELURAHAN' => $request->data_pelanggan['alamat_tagih']['kelurahan'],
            'INS_KECAMATAN' => $request->data_pelanggan['alamat_tagih']['kecamatan'],
            'INS_ZIP_CODE' => $request->data_pelanggan['alamat_tagih']['kode_pos'],
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
            'MAIL_ADDRESS' => $request->data_tambahan['surat']['alamat'],
            'MAIL_RT' => $request->data_tambahan['surat']['rt'],
            'MAIL_RW' => $request->data_tambahan['surat']['rw'],
            'MAIL_PROVINCE' => $request->data_tambahan['surat']['provinsi'],
            'MAIL_CITY' => $request->data_tambahan['surat']['kota'],
            'MAIL_KELURAHAN' => $request->data_tambahan['surat']['kelurahan'],
            'MAIL_KECAMATAN' => $request->data_tambahan['surat']['kecamatan'],
            'MAIL_ZIP_CODE' => $request->data_tambahan['surat']['kode_pos'],
            'EMERGENCY_NAME' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['nama'],
            'EMERGENCY_ADDRESS' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['alamat'],
            'EMERGENCY_RT' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['rt'],
            'EMERGENCY_RW' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['rw'],
            'EMERGENCY_PROVINCE' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['provinsi'],
            'EMERGENCY_CITY' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['kota'],
            'EMERGENCY_KELURAHAN' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['kelurahan'],
            'EMERGENCY_KECAMATAN' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['kecamatan'],
            'EMERGENCY_ZIP_CODE' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['kode_pos'],
            'EMERGENCY_PHONE_HOUSE' => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['no_telp'],
            'EMERGENCY_PHONE_PERSONAL'  => $request->data_tambahan['kerabat_dalam_kondisi_darurat']['no_hp'] 
        ];

         M_CrPersonal::create($data_cr_application);
    }

    private function insert_bank_account($request,$applicationId){

        if (isset($request->data_tambahan['bank']) && is_array($request->data_tambahan['bank'])) {
            foreach ($request->data_tambahan['bank'] as $result) {
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

   
}

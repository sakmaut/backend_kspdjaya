<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_Payment;
use App\Models\M_PaymentApproval;
use App\Models\M_PaymentAttachment;
use App\Models\M_PaymentDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function inquiryList(Request $request)
    {
        try {
            $results = DB::table('credit as a')
                            ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                            ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                            ->leftJoin('branch as d', 'd.ID', '=', 'a.BRANCH')
                            ->select(   'a.ID as creditId',
                                        'a.LOAN_NUMBER', 
                                        'a.ORDER_NUMBER', 
                                        'b.ID as custId', 
                                        'b.CUST_CODE', 
                                        'b.NAME as customer_name',
                                        'c.POLICE_NUMBER', 
                                        'a.INSTALLMENT_DATE', 
                                        'd.NAME as branch_name')
                            ->orderBy('a.ORDER_NUMBER', 'asc')
                            ->get();

            $mapping = $results->map(function($list){
                return [
                    'credit_id' => $list->creditId??'',
                    'loan_number' => $list-> LOAN_NUMBER ?? '',
                    'order_number' => $list-> ORDER_NUMBER ?? '',
                    'cust_id' => $list-> custId ?? '',
                    'cust_code' => $list-> CUST_CODE ?? '',
                    'customer_name' => $list-> customer_name ?? '',
                    'police_number' => $list-> POLICE_NUMBER ?? '',
                    'entry_date' => date('Y-m-d',strtotime($list->INSTALLMENT_DATE)) ?? '',
                    'branch_name' => $list-> branch_name ?? '',
                ];
            });

            return response()->json($mapping, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pinjaman(Request $request,$id)
    {
        try {
            $results = M_Credit::where('ID',$id)->first();

            if(!$results){
                $buildArray = [];
            }else{
                $buildArray =[
                    'status' => $results->STATUS??'',
                    'loan_number' => $results-> LOAN_NUMBER ?? '',
                    'cust_code' => $results-> CUST_CODE ?? '',
                    'branch_name' => M_Branch::find($results->BRANCH)->NAME??'',
                    'order_number' => $results-> ORDER_NUMBER ?? '',
                    'credit_type' => $results-> CREDIT_TYPE ?? '',
                    'tenor' => (int)$results-> PERIOD ?? 0,
                    'installment_date' => date('Y-m-d',strtotime($results->INSTALLMENT_DATE)) ?? '',
                    'installment' => floatval($results->INSTALLMENT) ?? 0,
                    'pcpl_ori' => floatval($results->PCPL_ORI) ?? 0,
                    'paid_principal' => floatval($results->PAID_PRINCIPAL) ?? 0,
                    'paid_interest' => floatval($results->PAID_INTEREST) ?? 0,
                    'paid_penalty' => floatval($results->PAID_PENALTY) ?? 0,
                    'mcf_name' => User::find($results->MCF_ID)->fullname??'',
                    'created_by' => User::find($results->CREATED_BY)->fullname??'',
                    'created_at' => date('Y-m-d',strtotime($results->CREATED_AT)) ??''
                ];
            }

            return response()->json($buildArray, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function debitur(Request $request,$id)
    {
        try {
            $results = DB::table('customer as a')
                        ->leftJoin('customer_extra as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                        ->select('a.*', 'b.*')
                        ->where('a.ID', $id)
                        ->first();

            if(!$results){
                $results = [];
            }else{
                $results = [
                    'pelanggan' => [
                        "nama" => $results->NAME ?? '',
                        "nama_panggilan" => $results->ALIAS ?? '',
                        "jenis_kelamin" => $results->GENDER ?? '',
                        "tempat_lahir" => $results->BIRTHPLACE ?? '',
                        "tgl_lahir" => date('Y-m-d',strtotime($results->BIRTHDATE)),
                        "gol_darah" => $results->BLOOD_TYPE ?? '',
                        "status_kawin" => $results->MARTIAL_STATUS ?? '',
                        "tgl_kawin" => $results->MARTIAL_DATE ?? '',
                        "tipe_identitas" => $results->ID_TYPE ?? '',
                        "no_identitas" => $results->ID_NUMBER ?? '',
                        "no_kk" => $results->KK_NUMBER ?? '',
                        "tgl_terbit_identitas" => date('Y-m-d', strtotime($results->ID_ISSUE_DATE))?? '',
                        "masa_berlaku_identitas" => date('Y-m-d', strtotime($results->ID_VALID_DATE)) ?? '',
                        "no_kk" => $results->KK_NUMBER??'',
                        "warganegara" => $results->CITIZEN ?? ''
                    ],
                    'alamat_identitas' => [
                        "alamat" => $results->ADDRESS ?? '',
                        "rt" => $results->RT ?? '',
                        "rw" => $results->RW ?? '',
                        "provinsi" => $results->PROVINCE ?? '',
                        "kota" => $results->CITY ?? '',
                        "kelurahan" => $results->KELURAHAN ?? '',
                        "kecamatan" => $results->KECAMATAN ?? '',
                        "kode_pos" => $results->ZIP_CODE ?? ''
                    ],
                    'alamat_tagih' => [
                        "alamat" => $results->INS_ADDRESS ?? '',
                        "rt" => $results->INS_RT ?? '',
                        "rw" => $results->INS_RW ?? '',
                        "provinsi" => $results->INS_PROVINCE ?? '',
                        "kota" => $results->INS_CITY ?? '',
                        "kelurahan" => $results->INS_KELURAHAN ?? '',
                        "kecamatan" => $results->INS_KECAMATAN ?? '',
                        "kode_pos" => $results->INS_ZIP_CODE ?? ''
                    ],
                    'pekerjaan' => [
                        "pekerjaan" => $results->OCCUPATION ?? '',
                        "pekerjaan_id" => $results->OCCUPATION_ON_ID ?? '',
                        "agama" => $results->RELIGION ?? '',
                        "pendidikan" => $results->EDUCATION ?? '',
                        "status_rumah" => $results->PROPERTY_STATUS ?? '',
                        "telepon_rumah" => $results->PHONE_HOUSE ?? '',
                        "telepon_selular" =>  $results->PHONE_PERSONAL ?? '',
                        "telepon_kantor" => $results->PHONE_OFFICE ?? '',
                        "ekstra1" => $results->EXT_1 ?? '',
                        "ekstra2" => $results->EXT_2 ?? ''
                    ],
                    'kerabat_darurat' => [
                        "nama"  => $results->EMERGENCY_NAME ?? '',
                        "alamat"  => $results->EMERGENCY_ADDRESS ?? '',
                        "rt"  => $results->EMERGENCY_RT ?? '',
                        "rw"  => $results->EMERGENCY_RW ?? '',
                        "provinsi" => $results->EMERGENCY_PROVINCE ?? '',
                        "kota" => $results->EMERGENCY_CITY ?? '',
                        "kelurahan" => $results->EMERGENCY_KELURAHAN ?? '',
                        "kecamatan" => $results->EMERGENCY_KECAMATAN ?? '',
                        "kode_pos" => $results->EMERGENCY_ZIP_CODE ?? '',
                        "no_telp" => $results->EMERGENCY_PHONE_HOUSE ?? '',
                        "no_hp" => $results->EMERGENCY_PHONE_PERSONAL ?? '',
                    ]
                ];
            }

            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function jaminan(Request $request,$id)
    {
        try {
            $results = M_CrCollateral::where('CR_CREDIT_ID',$id)->get();
            $results2 = M_CrCollateralSertification::where('CR_CREDIT_ID',$id)->get();

            $results = $results->map(function ($item) {
                $item->COLLATERAL_TYPE = 'kendaraan';
                $item->COLLATERAL_FLAG = M_Branch::find($item->COLLATERAL_FLAG)->NAME ?? '';
                $item->LOCATION_BRANCH = M_Branch::find($item->LOCATION_BRANCH)->NAME??'';
                $item->VALUE = floatval($item->VALUE);
                // Convert null values to empty strings for all keys
                $itemArray = $item->toArray();
                $itemArray = array_map(function ($value) {
                        return $value === null ? '' : $value;
                    }, $itemArray);

                return collect($itemArray)->except([
                    'CREATE_DATE',
                    'CREATE_BY',
                    'MOD_DATE',
                    'MOD_BY',
                    'DELETED_AT',
                    'DELETED_BY',
                    'VERSION'
                ]);
            })->values();

            $results2 = $results2->map(function ($item) {
                $item->COLLATERAL_TYPE = 'sertifikat';
                $item->COLLATERAL_FLAG = M_Branch::find($item->COLLATERAL_FLAG)->NAME ?? '';
                $item->LOCATION = M_Branch::find($item->LOCATION)->NAME ?? '';
                $item->NILAI = floatval($item->NILAI);
                // Convert null values to empty strings for all keys
                $itemArray = $item->toArray();
                $itemArray = array_map(function ($value) {
                        return $value === null ? '' : $value;
                    }, $itemArray);

                return collect($itemArray)->except([
                    'CREATE_DATE',
                    'CREATE_BY',
                    'MOD_DATE',
                    'MOD_BY',
                    'DELETED_AT',
                    'DELETED_BY',
                    'VERSION'
                ]);
            })->values();
            
            $mergedResults = $results->merge($results2);

            return response()->json($mergedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pembayaran(Request $request,$id)
    {
        try {
            $results = M_Payment::where('LOAN_NUM',$id)->first();

            if(!$results){
                $allData = [];
            }else{
                $branch = M_Branch::where('CODE_NUMBER', $results->BRANCH)->first();
                $results->BRANCH = $branch->NAME ?? '';
                $results->ORIGINAL_AMOUNT = floatval($results->ORIGINAL_AMOUNT) ?? 0;
                $results->OS_AMOUNT = floatval($results->OS_AMOUNT) ?? 0;
                $results->USER_ID = User::find($results->USER_ID)->fullname ?? '';
                $results->APPROVAL = M_PaymentApproval::where('PAYMENT_ID', $results->ID)->get() ?? '';
                $results->ATTACHMENT = M_PaymentAttachment::where('payment_id', $results->ID)->get()?? '';
                $results->DETAIL = M_PaymentDetail::where('PAYMENT_ID', $results->ID)->get() ?? '';

                $allData = [$results];
            }
           
            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function tunggakkan(Request $request,$id)
    {
        try {
            $results = M_Arrears::where('LOAN_NUMBER', $id)->first();

            if (!$results) {
                $allData = [];
            } else {
                $allData = $results;
            }
           
            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_CrSurveyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BpkbController extends Controller
{
    public function index(Request $request)
    {
        try {

            $branch = $request->user()->branch_id;

            $collateral = M_CrCollateral::where('LOCATION_BRANCH',$branch)->where(function($query) {
                                $query->whereNull('DELETED_AT')
                                    ->orWhere('DELETED_AT', '');
                            })->get(); 

            $collateral_sertificat = M_CrCollateralSertification::where('LOCATION',$branch)->where(function($query) {
                                        $query->whereNull('DELETED_AT')
                                            ->orWhere('DELETED_AT', '');
                                    })->get(); 

            $data = [];
            foreach ($collateral as $list) {

                $surveyId = M_CrApplication::select('CR_SURVEY_ID', 'credit.ORDER_NUMBER','customer.NAME')
                            ->join('credit', 'cr_application.ORDER_NUMBER', '=', 'credit.ORDER_NUMBER')
                            ->join('customer', 'credit.CUST_CODE', '=', 'customer.CUST_CODE')
                            ->where('credit.ID', $list->CR_CREDIT_ID)
                            ->first();

                $brachName = M_Branch::find($list->LOCATION_BRANCH);

                $data[] = [
                    "type" => "kendaraan",
                    'nama_debitur' => $surveyId->NAME??NULL,
                    'order_number' => $surveyId->ORDER_NUMBER??NULL,
                    'no_jaminan' => $list->BPKB_NUMBER??NULL,
                    'id' => $list->ID,
                    'status_jaminan' => $list->STATUS,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    "lokasi" => $brachName->NAME??null,
                    "document" => $this->attachment_guarante($surveyId?$surveyId->CR_SURVEY_ID:0,"'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'")
                ];    
            }
    
            foreach ($collateral_sertificat as $list) {

                $surveyId = M_CrApplication::select('CR_SURVEY_ID', 'credit.ORDER_NUMBER','customer.NAME')
                                            ->join('credit', 'cr_application.ORDER_NUMBER', '=', 'credit.ORDER_NUMBER')
                                            ->join('customer', 'credit.CUST_CODE', '=', 'customer.CUST_CODE')
                                            ->where('credit.ID', $list->CR_CREDIT_ID)
                                            ->first();
                
                $brachName = M_Branch::find($list->LOCATION_BRANCH);

                $data[] = [
                    "type" => "sertifikat",
                    'nama_debitur' => $surveyId->NAME??NULL,
                    'order_number' => $surveyId->ORDER_NUMBER??NULL,
                    'no_jaminan' => $list->NO_SERTIFIKAT??NULL,
                    'id' => $list->ID,
                    'status_jaminan' => $list->STATUS,
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
                    "nilai" => (int) $list->NILAI,
                    "lokasi" => $brachName->NAME??null,
                    "document" => $this->attachmentSertifikat($surveyId?$surveyId->CR_SURVEY_ID:0, ['sertifikat'])??null,
                ];    
            }

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function attachment_guarante($survey_id, $data){
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );
    
        return $documents;        
    }

    public function attachmentSertifikat($survey_id,$array = []){
        $attachment = M_CrSurveyDocument::where('CR_SURVEY_ID', $survey_id)
                    ->whereIn('TYPE', $array)
                    ->orderBy('TIMEMILISECOND', 'desc')
                    ->get();

    return $attachment;
}
}

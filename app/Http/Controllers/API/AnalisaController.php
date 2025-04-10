<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_ScBackground;
use App\Models\M_ScBusiness;
use App\Models\M_ScBusinessPattern;
use App\Models\M_ScBusinessProcess;
use App\Models\M_ScMarketing;
use App\Models\M_Scoring;
use Illuminate\Http\Request;

class AnalisaController extends Controller
{
    public function store(Request $req)
    {

        $datas = [
            'EMPLOYEE_ID' => $req->employee_id,
            'APPLICATION_ID' => $req->app_id,
            'ENTRY_DATE' => $req->entry_date,
            'RESULT' => $req->result,
            'DESCR' => $req->descr
        ];

        $execute = M_Scoring::create($datas);

        $data_bckground = [
            'SC_SCORING_ID' => $execute->ID,
            'attitude_during_interview' => $req->latar_belakang['sikap_debitur'],
            'data_providing_ease' => $req->latar_belakang['kemudahan_data'],
            'slik_reputation' => $req->latar_belakang['reputasi_slik'],
            'residence_status' => $req->latar_belakang['rumah_tinggal'],
            'key_business_actors' => $req->latar_belakang['aktor_penting'],
            'residential_environment' => $req->latar_belakang['lingkungan'],
            'description' => $req->latar_belakang['keterangan']
        ];

        M_ScBackground::create($data_bckground);

        $data_bisnis = [
            'SC_SCORING_ID' => $execute->ID,
            'business_location'  => $req->aspek_usaha['tempat_usaha'],
            'supplier_sources'  => $req->aspek_usaha['supplier'],
            'business_location_condition'  => $req->aspek_usaha['kondisi_lokasi'],
            'facilities_infrastructure'  => $req->aspek_usaha['sarana_prasarana'],
            'number_of_employees'  => $req->aspek_usaha['jumlah_karyawan'],
            'supplier_dependency'  => $req->aspek_usaha['ketergantungan_supplier'],
            'description' => $req->aspek_usaha['keterangan']
        ];

        M_ScBusiness::create($data_bisnis);

        $data_marketing = [
            'SC_SCORING_ID' => $execute->ID,
            'product_type' => $req->aspek_pemasaran['jenis_barang'],
            'marketing_area' => $req->aspek_pemasaran['daerah_pemasaran'],
            'buyer_dependency' => $req->aspek_pemasaran['ketergantungan_buyer'],
            'competition_level' => $req->aspek_pemasaran['tingkat_persaingan'],
            'market_strategy' => $req->aspek_pemasaran['strategi_pasar'],
            'description' => $req->aspek_pemasaran['keterangan']
        ];

        M_ScMarketing::create($data_marketing);

        $data_bisnis_process = [
            'SC_SCORING_ID' => $execute->ID,
            'product_type' => $req->aspek_bisnis['jenis_barang'],
            'marketing_area' => $req->aspek_bisnis['daerah_pemasaran'],
            'buyer_dependency' => $req->aspek_bisnis['ketergantungan_buyer'],
            'competition_level' => $req->aspek_bisnis['tingkat_persaingan'],
            'market_strategy' => $req->aspek_bisnis['strategi_pasar'],
            'description' => $req->aspek_bisnis['keterangan']
        ];

        M_ScBusinessProcess::create($data_bisnis_process);

        $data_bisnis_pattern = [
            'SC_SCORING_ID' => $execute->ID,
            'marketing_cycle' => $req->pola_bisnis['siklus_pemasaran'],
            'payment_method' => $req->pola_bisnis['cara_pembayaran'],
            'description' => $req->pola_bisnis['keterangan']
        ];

        M_ScBusinessPattern::create($data_bisnis_pattern);

        if ($execute) {
            return response()->json(['message' => 'Record created successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to create record'], 500);
        }
    }
}

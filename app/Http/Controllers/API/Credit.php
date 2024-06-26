<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrPersonal;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Credit extends Controller
{
    public function index(Request $request)
    {
        try {
            // $check = M_CrApplication::where('ORDER_NUMBER',$request->order_number)->first();

            // if (!$check) {
            //     throw new Exception("Order Number Is Not Exist", 404);
            // }
    
            return response()->json(['response' => self::buildData($request)], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    function buildData($request){
        // $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        // $cr_guarante_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->first();

        $principal = $request->pokok_pinjaman;
        $annualInterestRate = 40;
        $loanTerm = $request->tenor;

        $amortization = generateAmortizationSchedule($principal, $annualInterestRate, $loanTerm);

        // $data = [
        //     "no_perjanjian" => generateCode($request, 'credit', 'LOAN_NUMBER'),
        //      "pihak_1" => [
        //         "nama" => "",
        //         "jabatan" => "",
        //         "alamat_kantor" => ""
        //      ],
        //      "pihak_2" => [
        //         "nama" => $cr_personal->NAME,
        //         "no_identitas" => $cr_personal->ID_NUMBER,
        //         "alamat" => $cr_personal->ADDRESS
        //      ],
        //      "pokok_margin" => "",
        //      "tenor" => $data->PERIOD,
        //      "tgl_awal_cicilan" => $request->tgl_awal,
        //      "tgl_akhir_cicilan" => Carbon::parse($request->tgl_awal)->addMonths($data->PERIOD)->format('Y-m-d'),
        //      "angsuran" =>'Rp. '.number_format(round($data->INSTALLMENT,2)).' ('.strtoupper(angkaKeKata(round($data->INSTALLMENT,2))).')',
        //      "opt_periode" => $data->OPT_PERIODE,
        //      "tipe_jaminan" => $data->CREDIT_TYPE,
        //      "no_bpkb" =>  $cr_guarante_vehicle->BPKB_NUMBER,
        //      "atas_nama" => "",
        //      "merk" => $cr_guarante_vehicle->BRAND,
        //      "type" => $cr_guarante_vehicle->TYPE,
        //      "tahun" => $cr_guarante_vehicle->PRODUCTION_YEAR,
        //      "warna" => $cr_guarante_vehicle->COLOR,
        //      "no_polisi" => $cr_guarante_vehicle->POILICE_NUMBER,
        //      "no_rangka" =>$cr_guarante_vehicle->CHASIS_NUMBER,
        //      "no_mesin" => $cr_guarante_vehicle->ENGINE_NUMBER
        // ];

        return $amortization;
    }
}

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
            $check = M_CrApplication::where('ORDER_NUMBER',$request->order_number)->first();

            if (!$check) {
                throw new Exception("Order Number Is Not Exist", 404);
            }
    
            return response()->json(['response' =>self::buildData($request,$check)], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    function queryKapos($branchID){
        $result = DB::table('users')
                    ->select('fullname', 'position', 'branch.address')
                    ->join('branch', 'branch.id', '=', 'users.branch_id')
                    ->where('branch.id', '=', $branchID)
                    ->where('users.position', '=', 'KAPOS')
                    ->first();

        return $result;
    }

    function buildData($request,$data){
        $cr_personal = M_CrPersonal::where('APPLICATION_ID',$data->ID)->first();
        $cr_guarante_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$data->CR_SURVEY_ID)->first();
        $pihak1= self::queryKapos($data->BRANCH);

        $principal = $data->SUBMISSION_VALUE + $data->NET_ADMIN;
        $annualInterestRate = 40;
        $loanTerm = $data->PERIOD;
        $angsuran = $request->angsuran;

        $data = [
            "no_perjanjian" => generateCode($request, 'credit', 'LOAN_NUMBER'),
             "pihak_1" => [
                "nama" => strtoupper($pihak1->fullname),
                "jabatan" => strtoupper($pihak1->position),
                "alamat_kantor" => strtoupper($pihak1->address)
             ],
             "pihak_2" => [
                "nama" =>strtoupper($cr_personal->NAME),
                "no_identitas" => strtoupper($cr_personal->ID_NUMBER),
                "alamat" => strtoupper($cr_personal->ADDRESS)
             ],
             "pokok_margin" => number_format($principal),
             "tenor" => $data->PERIOD,
             "tgl_awal_cicilan" => $request->tgl_awal,
             "tgl_akhir_cicilan" => Carbon::parse($request->tgl_awal)->addMonths($data->PERIOD)->format('Y-m-d'),
             "angsuran" =>'Rp. '.number_format(round($data->INSTALLMENT,2)).' ('.strtoupper(angkaKeKata(round($data->INSTALLMENT,2))).')',
             "opt_periode" => $data->OPT_PERIODE,
             "tipe_jaminan" => $data->CREDIT_TYPE,
             "no_bpkb" =>  $cr_guarante_vehicle->BPKB_NUMBER,
             "atas_nama" => "",
             "merk" => $cr_guarante_vehicle->BRAND,
             "type" => $cr_guarante_vehicle->TYPE,
             "tahun" => $cr_guarante_vehicle->PRODUCTION_YEAR,
             "warna" => $cr_guarante_vehicle->COLOR,
             "no_polisi" => $cr_guarante_vehicle->POILICE_NUMBER,
             "no_rangka" =>$cr_guarante_vehicle->CHASIS_NUMBER,
             "no_mesin" => $cr_guarante_vehicle->ENGINE_NUMBER,
             "struktur_kredit" => generateAmortizationSchedule($principal,$angsuran, $annualInterestRate, $loanTerm)
        ];

        return $data;
    }
}

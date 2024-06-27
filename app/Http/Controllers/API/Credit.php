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
    
            return response()->json(self::buildData($request,$check), 200);
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
        $annualInterestRate = $data->FLAT_RATE;
        $effRate = $data->EFF_RATE;
        $loanTerm = $data->PERIOD;
        $angsuran = $request->angsuran;

        // $principal = $request->pokok_pinjaman;
        // $annualInterestRate = $request->bunga;
        // $effRate = 40;
        // $loanTerm = $request->tenor;
        // $angsuran = $request->angsuran;

        $data = [
            "no_perjanjian" => generateCode($request, 'credit', 'LOAN_NUMBER'),
             "pihak_1" => [
                "nama" => strtoupper($pihak1->fullname)??null,
                "jabatan" => strtoupper($pihak1->position)??null,
                "alamat_kantor" => strtoupper($pihak1->address)??null
             ],
             "pihak_2" => [
                "nama" =>strtoupper($cr_personal->NAME)??null,
                "no_identitas" => strtoupper($cr_personal->ID_NUMBER)??null,
                "alamat" => strtoupper($cr_personal->ADDRESS)??null
             ],
             "pokok_margin" =>bilangan($principal)??null,
             "tenor" => bilangan($data->PERIOD,false)??null,
             "tgl_awal_cicilan" => $request->tgl_awal??null,
             "tgl_akhir_cicilan" => Carbon::parse($request->tgl_awal)->addMonths($data->PERIOD)->format('Y-m-d')??null,
             "angsuran" =>bilangan($angsuran)??null,
             "opt_periode" => $data->OPT_PERIODE??null,
             "tipe_jaminan" => $data->CREDIT_TYPE??null,
             "no_bpkb" =>  $cr_guarante_vehicle->BPKB_NUMBER??null,
             "atas_nama" => "",
             "merk" => $cr_guarante_vehicle->BRAND??null,
             "type" => $cr_guarante_vehicle->TYPE??null,
             "tahun" => $cr_guarante_vehicle->PRODUCTION_YEAR??null,
             "warna" => $cr_guarante_vehicle->COLOR??null,
             "no_polisi" => $cr_guarante_vehicle->POILICE_NUMBER??null,
             "no_rangka" =>$cr_guarante_vehicle->CHASIS_NUMBER??null,
             "no_mesin" => $cr_guarante_vehicle->ENGINE_NUMBER??null,
             "struktur" => generateAmortizationSchedule($principal,$angsuran, $annualInterestRate,$effRate, $loanTerm)??null
        ];

        return $data;
    }
}

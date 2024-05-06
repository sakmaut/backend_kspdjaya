<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_PayemtnDump;
use App\Models\M_PaymentDump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentDumpController extends Controller
{
    public function index(Request $req){
        $noLoan = $req->no_loan;

        $data =  M_PaymentDump::where('LOAN_NUM', $noLoan)
                    ->orderBy('LOAN_NUM')
                    ->orderBy('AUTH_DATE')
                    ->orderByRaw("str_to_date(lpad(AUTH_TIME,6,'0'),'%H%i%s')")
                    ->orderBy('OS_PCPL_AMOUNT')
                    ->get();

       
        if(!$data){
            return response()->json([
                'message' => 'No Loan Tidak Ada',
                "status" => 403
            ], 403);
        }

        $dto = R_PayemtnDump::collection($data);

        return response()->json(['message' => 'Ok', "status" => 200, 'response' =>  $dto], 200);
    }

}

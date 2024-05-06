<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_HistoryAcc;
use App\Models\M_HistoryAcc;
use Illuminate\Http\Request;

class HistoryAccController extends Controller
{
    public function index(Request $req){

        $data =  M_HistoryAcc::where('ACC_NUMBER', $req->acc_number)
                    ->orderBy('ACC_NUMBER')
                    ->orderBy('VALUE_DATE')
                    ->orderByRaw("str_to_date(lpad(ENTRY_TIME, 6, '0'), '%H%i%s')")
                    ->get();

        $dto = R_HistoryAcc::collection($data);

        return response()->json(['message' => 'Ok', "status" => 200, 'response' =>  $dto], 200);
    }
}

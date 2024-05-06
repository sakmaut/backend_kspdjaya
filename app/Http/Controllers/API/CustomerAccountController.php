<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAccountController extends Controller
{
    public function index(Request $request)
    {
        try {
           $data = DB::table('sg_se_m4cu as a')
                    ->join('sg_rt_m5re as b', 'b.RECUCO', '=', 'a.CUCODE')
                    ->select(
                        'a.CUCODE as CIF',
                        'a.CUNAME as DEBITUR',
                        'b.RECODE as REKENING',
                        DB::raw('CONCAT(a.CUADR1, " ", a.CUADR2, " ", a.CUADR3) as ALAMAT'),
                        DB::raw('RECLBL / 100 as SALDO')
                    )
                    ->get();

            return response()->json(['message' => 'OK', "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }
}

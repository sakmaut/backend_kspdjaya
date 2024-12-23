<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListBanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $arusKas = $this->queryArusKas();

            $datas = array_map(function($list) {

                $branch = M_Branch::where('CODE_NUMBER',$list->BRANCH)->first();

                return [
                    'JENIS' => $list->JENIS,
                    'TYPE' => $list->JENIS == 'PENCAIRAN'?"CASH-OUT":"CASH-IN",
                    'BRANCH' => $branch->NAME??null,
                    'ENTRY_DATE' => $list->ENTRY_DATE,
                    'ORIGINAL_AMOUNT' => floatval($list->ORIGINAL_AMOUNT)
                ];
            }, $arusKas);

            
            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function queryArusKas(){
        $result = DB::select(
                "select case when c.ID is not null and a.ACC_KEYS like '%POKOK%' then 'TUNGGAKAN_POKOK'
                            when c.ID is not null and a.ACC_KEYS like '%BUNGA%' then 'TUNGGAKAN_BUNGA'
                            when c.ID is null and a.ACC_KEYS like '%POKOK%' then 'BAYAR_POKOK'
                            when c.ID is null and a.ACC_KEYS like '%BUNGA%' then 'TUNGGAKAN_BUNGA'
                            else 'BAYAR_LAINNYA' end as JENIS, 
                        b.BRANCH, b.ENTRY_DATE, a.ORIGINAL_AMOUNT
                from payment_detail a
                    inner join payment b on b.ID = a.PAYMENT_ID
                    left join arrears c on c.ID = b.ARREARS_ID
                union

                SELECT 	'PENCAIRAN' as JENIS, b.CODE_NUMBER as BRANCH,a.CREATED_AT as ENTRY_DATE,a.PCPL_ORI as ORIGINAL_AMOUNT
                FROM 	credit a 
                        inner join branch b on b.id = a.BRANCH
                WHERE 	a.STATUS = 'A';
                "
        );

        return $result;
    }
}

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

            if(isset($request->dari) || isset($request->sampai)){
                $dateFrom = $request->dari;
                $dateTo = $request->sampai;
                $arusKas = $this->queryArusKas($dateFrom, $dateTo);
            }else{
                $date = isset($request->dari) ? $request->dari : now();
                $arusKas = $this->queryArusKas($date);
            }

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

    private function queryArusKas($dateFrom = null, $dateTo = null) {

        $query = "SELECT * 
              FROM (
                    SELECT 
                        CASE 
                            WHEN c.ID IS NOT NULL AND a.ACC_KEYS LIKE '%POKOK%' THEN 'TUNGGAKAN_POKOK'
                            WHEN c.ID IS NOT NULL AND a.ACC_KEYS LIKE '%BUNGA%' THEN 'TUNGGAKAN_BUNGA'
                            WHEN c.ID IS NULL AND a.ACC_KEYS LIKE '%POKOK%' THEN 'BAYAR_POKOK'
                            WHEN c.ID IS NULL AND a.ACC_KEYS LIKE '%BUNGA%' THEN 'TUNGGAKAN_BUNGA'
                            ELSE 'BAYAR_LAINNYA' 
                        END AS JENIS, 
                        b.BRANCH, b.ENTRY_DATE, a.ORIGINAL_AMOUNT
                    FROM 
                        payment_detail a
                        INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                        LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                    UNION
                    SELECT 
                        'PENCAIRAN' AS JENIS, 
                        b.CODE_NUMBER AS BRANCH,
                        a.CREATED_AT AS ENTRY_DATE,
                        a.PCPL_ORI AS ORIGINAL_AMOUNT
                    FROM 
                        credit a
                        INNER JOIN branch b ON b.id = a.BRANCH
                    WHERE 
                        a.STATUS = 'A'
              ) AS query";

            if ($dateFrom && $dateTo) {
                $query .= " WHERE (ENTRY_DATE LIKE :dateFrom OR ENTRY_DATE LIKE :dateTo)";
                $result = DB::select($query, [
                    'dateFrom' => $dateFrom . '%', 
                    'dateTo' => $dateTo . '%' 
                ]);
            } else {
                $query .= " WHERE ENTRY_DATE LIKE :date";
                $result = DB::select($query, ['date' => "%$dateFrom%"]);
            };

        return $result;
    }
}

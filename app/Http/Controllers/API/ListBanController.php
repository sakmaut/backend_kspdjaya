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
            $cabangId = $request->cabang_id;

            if ((isset($request->dari) && !empty($request->dari) && $request->dari !== null) || ( isset($request->sampai) && !empty($request->sampai) && $request->sampai !== null)) {
                $dateFrom = $request->dari;
                $dateTo = $request->sampai;
                $arusKas = $this->queryArusKas($cabangId,$dateFrom, $dateTo);
            }else{
                $date = isset($request->dari) ? $request->dari : now();
                $arusKas = $this->queryArusKas($cabangId,$date);
            }

            $datas = array_map(function($list) {

                $branch = M_Branch::where('CODE_NUMBER',$list->BRANCH)->first();

                return [
                    'JENIS' => $list->JENIS,
                    'TYPE' => $list->JENIS == 'PENCAIRAN'?"CASH-OUT":"CASH-IN",
                    'BRANCH' => $branch->NAME??null,
                    'ENTRY_DATE' => date('Y-m-d',strtotime($list->ENTRY_DATE)),
                    'ORIGINAL_AMOUNT' => floatval($list->ORIGINAL_AMOUNT)
                ];
            }, $arusKas);

            
            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function queryArusKas($cabangId = null,$dateFrom = null, $dateTo = null) {

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
                        b.BRANCH AS BRANCH, 
                        d.ID AS BRANCH_ID, 
                        b.ENTRY_DATE, 
                        a.ORIGINAL_AMOUNT
                    FROM 
                        payment_detail a
                        INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                        LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                        LEFT JOIN branch d on d.CODE_NUMBER = b.BRANCH
                    UNION
                    SELECT 
                        'PENCAIRAN' AS JENIS, 
                        b.CODE_NUMBER AS BRANCH,
                        b.ID AS BRANCH_ID, 
                        a.CREATED_AT AS ENTRY_DATE,
                        a.PCPL_ORI AS ORIGINAL_AMOUNT
                    FROM 
                        credit a
                        INNER JOIN branch b ON b.id = a.BRANCH
                    WHERE 
                        a.STATUS = 'A'
              ) AS query";

            $params = [];

            if ($dateFrom && $dateTo) {
                $query .= " WHERE DATE_FORMAT(ENTRY_DATE, '%Y-%m-%d') BETWEEN :dateFrom AND :dateTo";
                $params['dateFrom'] = $dateFrom;
                $params['dateTo'] = $dateTo;
            } else {
                $query .= " WHERE DATE_FORMAT(ENTRY_DATE, '%Y-%m-%d') = :dateFrom";
                $params['dateFrom'] = $dateFrom;
            }

            if (!empty($cabangId)) {
                $query .= empty($params) ? " WHERE" : " AND";
                $query .= " BRANCH_ID = :cabangId";
                $params['cabangId'] = $cabangId;
            }

            $result = DB::select($query, $params);

        return $result;
    }

    public function listBan() {

        $query = "SELECT    a.NAME, b.LOAN_NUMBER, c.NAME, b.CREATED_AT,
                            c.INS_ADDRESS, c.ZIP_CODE, c.PHONE_HOUSE,
                            c.PHONE_PERSONAL, c.OCCUPATION, d.fullname,
                            f.survey_note, b.PCPL_ORI, e.TOTAL_ADMIN, b.PERIOD,
                            DATEDIFF(b.FIRST_ARR_DATE,now()) as OVERDUE,
                            99 as CYCLE, b.STATUS_REC,
                            b.PAID_PRINCIPAL, b.PAID_INTEREST,
                            b.PAID_PRINCIPAL+b.PAID_INTEREST as PAID_TOTAL,
                            (b.PCPL_ORI-b.PAID_PRINCIPAL) as OUTSTANDING,
                            b.INSTALLMENT, b.INSTALLMENT_DATE,
                            b.FIRST_ARR_DATE, ' ' as COLLECTOR,
                            GROUP_CONCAT(concat(g.BRAND,' ',g.TYPE)) as COLLATERAL,
                            GROUP_CONCAT(g.POLICE_NUMBER) as POLICE_NUMBER,
                            GROUP_CONCAT(g.ENGINE_NUMBER) as ENGINE_NUMBER,
                            GROUP_CONCAT(g.CHASIS_NUMBER) as CHASIS_NUMBER,
                            GROUP_CONCAT(g.PRODUCTION_YEAR) as PRODUCTION_YEAR,
                            SUM(g.VALUE) as TOTAL_NILAI_JAMINAN,b.CUST_CODE
                FROM    branch a
                        inner join credit b on b.BRANCH = a.ID
                        left join customer c on c.CUST_CODE = b.CUST_CODE
                        left join users d on d.id = b.MCF_ID
                        left join cr_application e on e.ORDER_NUMBER = b.ORDER_NUMBER
                        left join cr_survey f on f.id = e.CR_SURVEY_ID
                        left join cr_collateral g on g.CR_CREDIT_ID = b.ID
                GROUP   BY  a.NAME, b.LOAN_NUMBER, c.NAME, b.CREATED_AT,
                            c.INS_ADDRESS, c.ZIP_CODE, c.PHONE_HOUSE,
                            c.PHONE_PERSONAL, c.OCCUPATION, d.fullname,
                            f.survey_note, b.PCPL_ORI, e.TOTAL_ADMIN, b.PERIOD,
                            DATEDIFF(b.FIRST_ARR_DATE,now()),
                            b.STATUS_REC, b.PAID_PRINCIPAL, b.PAID_INTEREST,
                            b.PAID_PRINCIPAL+b.PAID_INTEREST,
                            (b.PCPL_ORI-b.PAID_PRINCIPAL),
                            b.INSTALLMENT, b.INSTALLMENT_DATE,
                            b.FIRST_ARR_DATE, b.CUST_CODE
                LIMIT 1";


        $results = DB::select($query);

        $build = [];
        foreach ($results as $result) {
            $build[] =[
                "NAMA CABANG" =>$result->NAME
            ] ;
        }

        return $build;
    }
}

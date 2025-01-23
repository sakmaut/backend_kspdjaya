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

        $query = "  SELECT 
                        b.JENIS,
                        b.BRANCH,
                        b.BRANCH_ID,
                        b.ENTRY_DATE,
                        b.ORIGINAL_AMOUNT,
                        b.LOAN_NUM,
                        concat(b3.NAME,' ',b3.ALIAS) as PELANGGAN,
                        b.PAYMENT_METHOD
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
                            a.ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD
                        FROM 
                            payment_detail a
                            INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                            LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                            LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH

                        UNION ALL

                        SELECT 
                            'PENCAIRAN' AS JENIS, 
                            b.CODE_NUMBER AS BRANCH,
                            b.ID AS BRANCH_ID, 
                            a.CREATED_AT AS ENTRY_DATE,
                            a.PCPL_ORI AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            'cash' as PAYMENT_METHOD
                        FROM 
                            credit a
                            INNER JOIN branch b ON b.id = a.BRANCH
                        WHERE 
                            a.STATUS = 'A'
                    ) AS b
                    INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
                    INNER JOIN customer b3 on b3.CUST_CODE = b2.CUST_CODE
                    WHERE (1=1)";

            $params = [];

            if ($dateFrom && $dateTo) {
                $query .= " WHERE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') BETWEEN :dateFrom AND :dateTo";
                $params['dateFrom'] = $dateFrom;
                $params['dateTo'] = $dateTo;
            } else {
                $query .= " WHERE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') = :dateFrom";
                $params['dateFrom'] = $dateFrom;
            }

            if (!empty($cabangId)) {
                $query .= empty($params) ? " WHERE" : " AND";
                $query .= "b.BRANCH_ID = :cabangId";
                $params['cabangId'] = $cabangId;
            }

            $result = DB::select($query, $params);

        return $result;
    }

    public function listBan() {
        ini_set('memory_limit', '256M');

        try {
            $results = DB::table('branch as a')
                            ->join('credit as b', 'b.BRANCH', '=', 'a.ID')
                            ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'b.CUST_CODE')
                            ->leftJoin('users as d', 'd.id', '=', 'b.MCF_ID')
                            ->leftJoin('cr_application as e', 'e.ORDER_NUMBER', '=', 'b.ORDER_NUMBER')
                            ->leftJoin('cr_survey as f', 'f.id', '=', 'e.CR_SURVEY_ID')
                            ->leftJoin('cr_collateral as g', 'g.CR_CREDIT_ID', '=', 'b.ID')
                        ->select(
                            'a.CODE',
                            'a.NAME as cabang',
                            'b.LOAN_NUMBER',
                            'c.NAME as customer_name',
                            'b.CREATED_AT',
                            'c.INS_ADDRESS',
                            'c.ZIP_CODE',
                            'c.PHONE_HOUSE',
                            'c.PHONE_PERSONAL',
                            'c.OCCUPATION',
                            'd.fullname',
                            'f.survey_note',
                            'b.PCPL_ORI',
                            'e.TOTAL_ADMIN',
                            'e.INSTALLMENT_TYPE',
                            'b.PERIOD',
                            DB::raw('DATEDIFF(b.FIRST_ARR_DATE, NOW()) as OVERDUE'),
                            DB::raw('99 as CYCLE'),
                            'b.STATUS_REC',
                            'b.PAID_PRINCIPAL',
                            'b.PAID_INTEREST',
                            DB::raw('b.PAID_PRINCIPAL + b.PAID_INTEREST as PAID_TOTAL'),
                            DB::raw('b.PCPL_ORI - b.PAID_PRINCIPAL as OUTSTANDING'),
                            'b.INSTALLMENT',
                            'b.INSTALLMENT_DATE',
                            'b.FIRST_ARR_DATE',
                            DB::raw("' ' as COLLECTOR"),
                            DB::raw('GROUP_CONCAT(CONCAT(g.BRAND, " ", g.TYPE)) as COLLATERAL'),
                            DB::raw('GROUP_CONCAT(g.POLICE_NUMBER) as POLICE_NUMBER'),
                            DB::raw('GROUP_CONCAT(g.ENGINE_NUMBER) as ENGINE_NUMBER'),
                            DB::raw('GROUP_CONCAT(g.CHASIS_NUMBER) as CHASIS_NUMBER'),
                            DB::raw('GROUP_CONCAT(g.PRODUCTION_YEAR) as PRODUCTION_YEAR'),
                            DB::raw('SUM(g.VALUE) as TOTAL_NILAI_JAMINAN'),
                            'b.CUST_CODE'
                        )
                        ->groupBy(
                            'a.CODE',
                            'a.NAME',
                            'b.LOAN_NUMBER',
                            'c.NAME',
                            'b.CREATED_AT',
                            'c.INS_ADDRESS',
                            'c.ZIP_CODE',
                            'c.PHONE_HOUSE',
                            'c.PHONE_PERSONAL',
                            'c.OCCUPATION',
                            'd.fullname',
                            'f.survey_note',
                            'b.PCPL_ORI',
                            'e.TOTAL_ADMIN',
                            'e.INSTALLMENT_TYPE',
                            'b.PERIOD',
                            DB::raw('DATEDIFF(b.FIRST_ARR_DATE, NOW())'),
                            'b.STATUS_REC',
                            'b.PAID_PRINCIPAL',
                            'b.PAID_INTEREST',
                            DB::raw('b.PAID_PRINCIPAL + b.PAID_INTEREST'),
                            DB::raw('b.PCPL_ORI - b.PAID_PRINCIPAL'),
                            'b.INSTALLMENT',
                            'b.INSTALLMENT_DATE',
                            'b.FIRST_ARR_DATE',
                            'b.CUST_CODE'
                        )
                        ->get();

            $build = [];
            foreach ($results as $result) {
                $build[] =[
                    "KODE" => $result->CODE??'',
                    "CABANG" => $result->cabang??'',
                    "NO KONTRAK" => $result->LOAN_NUMBER??'',
                    "NAMA PELANGGAN" => $result->NAME??'',
                    "TGL BOOKING" => isset($result->CREATED_AT) && !empty($result->CREATED_AT) ? date("d-m-Y", strtotime($result->CREATED_AT)) : '',
                    "ALAMAT TAGIH" => $result->INS_ADDRESS??'',
                    "KODE POS" => $result->ZIP_CODE??'',
                    "NO TELP" => $result->PHONE_HOUSE??'',
                    "NO HP" => $result->PHONE_PERSONAL??'',
                    "PEKERJAAN" => $result->OCCUPATION??'',
                    "SURVEYOR" => $result->fullname??'',
                    "CATT SURVEY" => $result->survey_note??'',
                    "PKK HUTANG" => $result->PCPL_ORI??'',
                    "JML ANGS" => $result->PERIOD??'',
                    "PERIOD" => $result->INSTALLMENT_TYPE??'',
                    "OVERDUE" => $result->OVERDUE??'',
                    "CYCLE" => $result->CYCLE??'',
                    "STS KONTRAK" => $result->STATUS_REC??'',
                    "OUTS PKK AKHIR" => $result->PAID_PRINCIPAL??'',
                    "OUTS BNG_AKHIR" => $result->PAID_INTEREST??'',
                    "ANGSURAN" =>  $result->INSTALLMENT??'',
                    "JTH TEMPO AWAL" => date("d-m-Y",strtotime( $result->INSTALLMENT_DATE))??'',
                    "JTH TEMPO AKHIR" => date("d-m-Y",strtotime( $result->INSTALLMENT_DATE))??'',
                    "NAMA BRG" =>  "SEPEDA MOTOR",
                    "TIPE BRG" =>  $result->COLLATERAL??'',
                    "NO POL" =>  $result->POLICE_NUMBER??'',
                    "NO MESIN" =>  $result->ENGINE_NUMBER??'',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER??'',
                    "TAHUN" =>  $result->PRODUCTION_YEAR??'',
                    "NILAI PINJAMAN" =>  $result->TOTAL_NILAI_JAMINAN??'',
                    "ADMIN" =>  $result->TOTAL_ADMIN??'',
                    "CUST_ID" =>  $result->CUST_CODE??'',
                ] ;
            }
            return response()->json($build, 200);           
        }catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

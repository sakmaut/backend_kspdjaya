<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BungaMenurunController extends Controller
{
    public function checkCredit(Request $request)
    {
        try {

            $loan_number = $request->loan_number;
            $setPenaltyRate = 5;

            $allQuery = "SELECT 
                            (a.PCPL_ORI - COALESCE(a.PAID_PRINCIPAL, 0)) AS SISA_POKOK,
                            b.INT_ARR AS TUNGGAKAN_BUNGA,
                            e.TUNGGAKAN_DENDA AS TUNGGAKAN_DENDA,
                            e.DENDA_TOTAL AS DENDA,
                            (($setPenaltyRate / 100) * (a.PCPL_ORI - COALESCE(a.PAID_PRINCIPAL, 0))) AS PINALTI,
                            d.DISC_BUNGA
                        FROM 
                            credit a
                            LEFT JOIN (
                                SELECT 
                                    LOAN_NUMBER,
                                    SUM(COALESCE(INTEREST, 0)) - SUM(COALESCE(PAYMENT_VALUE_INTEREST, 0)) AS INT_ARR
                                FROM 
                                    credit_schedule
                                WHERE 
                                    LOAN_NUMBER = '{$loan_number}' 
                                    AND PAYMENT_DATE <= (
                                        SELECT COALESCE(
                                            MIN(PAYMENT_DATE), 
                                            (SELECT MAX(PAYMENT_DATE) 
                                            FROM credit_schedule 
                                            WHERE LOAN_NUMBER = '{$loan_number}'  
                                            AND PAYMENT_DATE < NOW())
                                        )
                                        FROM credit_schedule
                                        WHERE LOAN_NUMBER = '{$loan_number}' 
                                        AND PAYMENT_DATE > NOW()
                                    )
                                GROUP BY LOAN_NUMBER
                            ) b ON b.LOAN_NUMBER = a.LOAN_NUMBER
                             LEFT JOIN (
                                        SELECT
                                            LOAN_NUMBER,
                                            COALESCE(SUM(INTEREST), 0) AS DISC_BUNGA
                                        FROM
                                            credit_schedule
                                        WHERE
                                            LOAN_NUMBER = '{$loan_number}'
                                            AND PAYMENT_DATE > (
                                                SELECT COALESCE(MIN(PAYMENT_DATE),
                                                    (SELECT MAX(PAYMENT_DATE)
                                                    FROM credit_schedule
                                                    WHERE LOAN_NUMBER = '{$loan_number}'
                                                    AND PAYMENT_DATE < NOW())
                                                )
                                                FROM credit_schedule
                                                WHERE LOAN_NUMBER = '{$loan_number}'
                                                AND PAYMENT_DATE > NOW()
                                            )
                                        GROUP BY LOAN_NUMBER
                            ) AS d ON d.LOAN_NUMBER = a.LOAN_NUMBER
                            LEFT JOIN (
                                SELECT 
                                    LOAN_NUMBER, 
                                    SUM(CASE WHEN STATUS_REC <> 'A' THEN COALESCE(PAST_DUE_PENALTY, 0) END) -
                                        SUM(CASE WHEN STATUS_REC <> 'A' THEN COALESCE(PAID_PENALTY, 0) END) AS TUNGGAKAN_DENDA,
                                    SUM(COALESCE(PAST_DUE_PENALTY, 0)) - SUM(COALESCE(PAID_PENALTY, 0)) AS DENDA_TOTAL
                                FROM 
                                    arrears
                                WHERE 
                                    LOAN_NUMBER = '{$loan_number}' 
                                    AND STATUS_REC != 'PENDING'
                                GROUP BY LOAN_NUMBER
                            ) e ON e.LOAN_NUMBER = a.LOAN_NUMBER
                        WHERE 
                            a.LOAN_NUMBER = '{$loan_number}' ";

            $result = DB::select($allQuery);

            $processedResults = array_map(function ($item) {
                return [
                    'SISA_POKOK' => round(floatval($item->SISA_POKOK), 2),
                    'TUNGGAKAN_BUNGA' => round(floatval($item->TUNGGAKAN_BUNGA), 2),
                    'TUNGGAKAN_DENDA' => round(floatval($item->TUNGGAKAN_DENDA), 2),
                    'DENDA' => round(floatval($item->DENDA), 2),
                    'PINALTI' => round(floatval($item->PINALTI), 2),
                    'DISC_BUNGA' => round(floatval($item->DISC_BUNGA), 2)
                ];
            }, $result);

            // $discBunga = 0;
            // if (!empty($query2) && isset($query2[0]->DISC_BUNGA)) {
            //     $discBunga = round(floatval($query2[0]->DISC_BUNGA), 2);
            // }

            // foreach ($processedResults as &$processedResult) {
            //     $processedResult['DISC_BUNGA'] = $discBunga;
            // }

            return response()->json($processedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Payment\Repository;

use Illuminate\Support\Facades\DB;

class R_PokokSebagian
{
    public function getAllData($request)
    {
        $loan_number = $request->loan_number;
        $today = date('Y-m-d');

        $query = "  SELECT 
                        COALESCE(a.PCPL_ORI - COALESCE(a.PAID_PRINCIPAL, 0), 0) AS SISA_POKOK,
                        COALESCE(b.INT_ARR, 0) AS TUNGGAKAN_BUNGA,
                        COALESCE(e.TUNGGAKAN_DENDA, 0) AS TUNGGAKAN_DENDA,
                        COALESCE(e.DENDA_TOTAL, 0) AS DENDA,
                        COALESCE(d.DISC_BUNGA, 0) AS DISC_BUNGA
                    FROM credit a
                        LEFT JOIN (
                            SELECT 
                                LOAN_NUMBER,
                                SUM(COALESCE(INTEREST, 0)) - SUM(COALESCE(PAYMENT_VALUE_INTEREST, 0)) AS INT_ARR
                            FROM 
                                credit_schedule
                            WHERE 
                                LOAN_NUMBER = '{$loan_number}'
                                AND PAYMENT_DATE <= (
                                    SELECT 
                                        CASE 
                                            WHEN EXISTS (
                                                SELECT 1 
                                                FROM credit_schedule 
                                                WHERE LOAN_NUMBER = '{$loan_number}' 
                                                AND PAYMENT_DATE = '{$today}'
                                            ) THEN '{$today}'
                                            ELSE (
                                                SELECT MIN(PAYMENT_DATE)
                                                FROM credit_schedule 
                                                WHERE LOAN_NUMBER = '{$loan_number}' 
                                                AND PAYMENT_DATE > '{$today}'
                                            )
                                        END
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
                                                AND PAYMENT_DATE < '{$today}')
                                            )
                                            FROM credit_schedule
                                            WHERE LOAN_NUMBER = '{$loan_number}'
                                            AND PAYMENT_DATE > '{$today}'
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
                            WHERE LOAN_NUMBER = '{$loan_number}' 
                                AND STATUS_REC != 'PENDING'
                            GROUP BY LOAN_NUMBER
                        ) e ON e.LOAN_NUMBER = a.LOAN_NUMBER
                    WHERE 
                        a.LOAN_NUMBER = '{$loan_number}' ";

        $result = DB::select($query);

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

        return $processedResults;
    }
}

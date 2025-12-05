<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InsertDailyInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-daily-interest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert daily interest transaction (only today data)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::statement("
            INSERT INTO credit_transaction_interest (
                ID,
                LOAN_NUMBER,
                ACC_KEYS,
                START_DATE,
                COUNT_DAY,
                INTEREST_RATE,
                PRINCIPAL,
                DAILY_INTEREST,
                TOTAL_DAILY_INTEREST,
                CREATED_BY,
                CREATED_AT
            )
            WITH RECURSIVE tanggal_harian AS (
                SELECT
                    LOAN_NUMBER,
                    'BUNGA_HARIAN' AS ket,
                    (PCPL_ORI - COALESCE(PAID_PRINCIPAL, 0)) AS sisa_pokok,
                    DATE(created_at) AS start_date,
                    INTEREST_RATE,
                    DATE(created_at) AS tgl,
                    0 AS hari_ke
                FROM credit
                WHERE CREDIT_TYPE = 'rekening_koran'

                UNION ALL

                SELECT
                    LOAN_NUMBER,
                    ket,
                    sisa_pokok,
                    start_date,
                    INTEREST_RATE,
                    DATE_ADD(tgl, INTERVAL 1 DAY),
                    hari_ke + 1
                FROM tanggal_harian
                WHERE tgl < CURDATE()
            )

            SELECT
                UUID() AS ID,
                LOAN_NUMBER,
                'BUNGA_HARIAN',
                start_date,
                hari_ke,
                INTEREST_RATE,
                ROUND(sisa_pokok, 2) AS PRINCIPAL,
                ROUND(
                    (sisa_pokok * ((INTEREST_RATE * 12) / 100) /
                        (CASE
                            WHEN (YEAR(tgl) % 400 = 0)
                              OR (YEAR(tgl) % 4 = 0 AND YEAR(tgl) % 100 <> 0)
                            THEN 366
                            ELSE 365
                        END)) *
                    CASE WHEN hari_ke = 0 THEN 0 ELSE 1 END,
                4) AS DAILY_INTEREST,
                ROUND(
                    (sisa_pokok * ((INTEREST_RATE * 12) / 100) /
                        (CASE
                            WHEN (YEAR(tgl) % 400 = 0)
                              OR (YEAR(tgl) % 4 = 0 AND YEAR(tgl) % 100 <> 0)
                            THEN 366
                            ELSE 365
                        END)) *
                    CASE WHEN hari_ke = 0 THEN 0 ELSE hari_ke END,
                4) AS TOTAL_DAILY_INTEREST,
                'CRON_JOB_SYSTEM',
                CURDATE()
            FROM tanggal_harian
            WHERE tgl = CURDATE()                        -- hanya hari ini
              AND NOT EXISTS (
                SELECT 1 FROM live_sfi.credit_transaction_interest cti
                WHERE cti.LOAN_NUMBER = tanggal_harian.LOAN_NUMBER
                AND DATE(cti.CREATED_AT) = CURDATE()     -- hindari duplicate
              )
            ORDER BY LOAN_NUMBER;
        ");
    }
}

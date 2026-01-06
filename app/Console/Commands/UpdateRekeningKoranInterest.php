<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateRekeningKoranInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-rekening-koran-interest';
    protected $description = 'Update interest & installment credit_schedule for rekening koran';

    public function handle()
    {
        DB::beginTransaction();

        try {
            $affected = DB::update("
                UPDATE credit_schedule cs
                JOIN (
                    SELECT
                        cs2.ID,
                        COALESCE(SUM(ci.DAILY_INTEREST), 0) AS CALCULATED_INTEREST
                    FROM credit_schedule cs2
                    JOIN credit c
                        ON c.LOAN_NUMBER = cs2.LOAN_NUMBER
                    LEFT JOIN credit_transaction_interest ci
                        ON ci.LOAN_NUMBER = cs2.LOAN_NUMBER
                       AND DATE(ci.CREATED_AT) >
                            COALESCE(
                                (
                                    SELECT MAX(prev.PAYMENT_DATE)
                                    FROM credit_schedule prev
                                    WHERE prev.LOAN_NUMBER = cs2.LOAN_NUMBER
                                      AND prev.PAYMENT_DATE < cs2.PAYMENT_DATE
                                ),
                                '1900-01-01'
                            )
                       AND DATE(ci.CREATED_AT) <= cs2.PAYMENT_DATE
                    WHERE c.CREDIT_TYPE = 'rekening_koran'
                      AND c.STATUS = 'A'
                    GROUP BY cs2.ID
                ) x
                    ON x.ID = cs.ID
                SET
                    cs.INTEREST    = x.CALCULATED_INTEREST,
                    cs.INSTALLMENT = x.CALCULATED_INTEREST");

            DB::commit();

            Log::info('Rekening koran interest updated', [
                'rows' => $affected
            ]);

            $this->info("Success update {$affected} rows");
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Rekening koran interest cron failed', [
                'error' => $e->getMessage()
            ]);

            $this->error($e->getMessage());
        }
    }
}

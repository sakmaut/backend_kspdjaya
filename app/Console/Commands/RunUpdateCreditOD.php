<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunUpdateCreditOD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-update-credit-o-d';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::transaction(function () {

            DB::statement("TRUNCATE temp_credit_od");

            DB::statement("INSERT INTO temp_credit_od
            SELECT a.LOAN_NUMBER, b.OD,
            MAX(
                CASE 
                    WHEN DATEDIFF(
                        COALESCE(DATE_FORMAT(mp.ENTRY_DATE,'%Y-%m-%d'),DATE_FORMAT(NOW(),'%Y-%m-%d')),
                        a.PAYMENT_DATE
                    ) < 0 THEN 0
                    ELSE DATEDIFF(
                        COALESCE(DATE_FORMAT(mp.ENTRY_DATE,'%Y-%m-%d'),DATE_FORMAT(NOW(),'%Y-%m-%d')),
                        a.PAYMENT_DATE
                    )
                END
            ) AS OD_NEW
            FROM credit_schedule a
            INNER JOIN credit b ON b.LOAN_NUMBER = a.LOAN_NUMBER
            ...
            GROUP BY a.LOAN_NUMBER
        ");

            DB::statement("UPDATE credit a
                INNER JOIN temp_credit_od tco 
                    ON a.LOAN_NUMBER = tco.LOAN_NUMBER
                SET a.OD = tco.OD_NEW
                WHERE a.OD < tco.OD_NEW
            ");
        });
    }
}

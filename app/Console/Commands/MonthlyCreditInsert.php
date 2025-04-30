<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonthlyCreditInsert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:last-monthly-credit-insert';

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
        $now = Carbon::now();
        $isLastDay = $now->isLastOfMonth() && $now->format('H:i') === '22:30';
        $isFirstDay = $now->day === 1 && $now->format('H:i') === '03:00';

        if ($isLastDay || $isFirstDay) {
            try {
                DB::statement('CALL insert_credit_data_2025()');
                $this->info("Stored Procedure executed on: " . $now->toDateTimeString());
            } catch (\Exception $e) {
                $this->error("Error executing stored procedure: " . $e->getMessage());
            }
        } 
    }
}

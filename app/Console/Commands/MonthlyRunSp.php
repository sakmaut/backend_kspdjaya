<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonthlyRunSp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monthly-run-sp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    public function handle()
    {
        $now = Carbon::now('Asia/Jakarta')->format('dmY');

        try {
            DB::select('CALL initialListbanOngoing(?, ?)', [$now, "BOTEX_CRON_JOB"]);
        } catch (\Exception $e) {
            $this->error("Error executing stored procedure: " . $e->getMessage());
        }
    }
}

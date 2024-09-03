<?php

namespace App\Console\Commands;

use App\Models\M_Branch;
use App\Models\M_Test;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Log\Logger;

class DemoCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:cron';

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
        try {
            $data = [
                'CODE' => 'value1',
                'CODE_NUMBER' => 123455,
                'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                'CREATE_USER' =>''
            ];

            M_Branch::create($data);

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

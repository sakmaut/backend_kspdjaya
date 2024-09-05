<?php

namespace App\Console\Commands;

use App\Models\M_Branch;
use App\Models\M_Test;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Log\Logger;
use Ramsey\Uuid\Uuid;

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
        $data = [
            'CODE' =>  Uuid::uuid7()->toString(),
            'NAME' =>  'BACOT',
            'CODE_NUMBER' => Uuid::uuid7()->toString(),
            'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
            'CREATE_USER' =>'',
            'DELETED_AT' => Carbon::now()
        ];

        M_Branch::create($data);
    }
}

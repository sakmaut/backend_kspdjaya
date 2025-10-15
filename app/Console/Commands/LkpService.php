<?php

namespace App\Console\Commands;

use App\Models\M_Lkp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LkpService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:lkp-service';

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
        $results = M_Lkp::where('STATUS', 'Active')
            ->whereDate('CREATED_AT', Carbon::today())
            ->get();

        if ($results->isNotEmpty()) {
            foreach ($results as $row) {
                $row->update([
                    'STATUS' => 'Inactive',
                    'UPDATED_BY' => 'SYSTEM',
                    'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
                ]);
            }
        }
    }
}

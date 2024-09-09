<?php

namespace App\Console\Commands;

use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CronJobLog;
use App\Models\M_Test;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
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
        try {
            $query = DB::table('credit_schedule')
                ->where('PAYMENT_DATE', '<', DB::raw('CURDATE()'))
                ->where('PAID_FLAG', '')
                ->select('*')
                ->get();

            if (!$query->isEmpty()) {
                $arrearsData = [];
                foreach ($query as $result) {
    
                    $startDate = $result->PAYMENT_DATE;
                    $endDate = date('Y-m-d'); // current date
                    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    
                    $pastDuePinalty = $result->INSTALLMENT * ($daysDiff * 0.005);
    
                    $arrearsData[] = [
                        'ID' => Uuid::uuid7()->toString(),
                        'STATUS_REC' => 'A',
                        'LOAN_NUMBER' => $result->LOAN_NUMBER,
                        'START_DATE' => $result->PAYMENT_DATE,
                        'END_DATE' => null,
                        'PAST_DUE_PCPL' => $result->INSTALLMENT,
                        'PAST_DUE_PENALTY' => $pastDuePinalty,
                        'CREATED_AT' => Carbon::now()
                    ];
                }
        
                $process = M_Arrears::insert($arrearsData);
    
                if($process){
                    M_CronJobLog::create([
                        'STATUS' => 'SUCCESS',
                        'DESCRIPTION' => 'SUCCESS'
                    ]);
                }
            }else{
                M_CronJobLog::create([
                    'STATUS' => 'SUCCESS',
                    'DESCRIPTION' => 'DATA ARREARS EMPTY'
                ]);
            }            
        } catch (\Illuminate\Database\QueryException $e) {
            M_CronJobLog::create([
                'STATUS' => 'FAIL',
                'DESCRIPTION' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            M_CronJobLog::create([
                'STATUS' => 'FAIL',
                'DESCRIPTION' => $e->getMessage()
            ]);
        }
    }
}

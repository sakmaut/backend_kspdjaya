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

            // $setDAte = DB::raw('CURDATE()');
            $setDate = '2023-12-16';

            $query = DB::table('credit_schedule')
                        ->where('PAYMENT_DATE', '<=', $setDate)
                        ->where(function ($query) {
                            $query->whereNull('PAID_FLAG')
                                ->orWhere('PAID_FLAG', '=', '');
                        })
                        ->select('*')
                        ->get();
    
            if ($query->isEmpty()) {
                M_CronJobLog::create([
                    'STATUS' => 'SUCCESS',
                    'DESCRIPTION' => 'DATA ARREARS EMPTY'
                ]);
                return;
            }
    
            $arrearsData = [];
            foreach ($query as $result) {
                $date = date('Y-m-d');
                $dateCurrnt = date('2023-12-16');
                $daysDiff = (strtotime($dateCurrnt) - strtotime($result->PAYMENT_DATE)) / (60 * 60 * 24);
                $pastDuePenalty = $result->INSTALLMENT * ($daysDiff * 0.005);
    
                $arrearsData[] = [
                    'ID' => Uuid::uuid7()->toString(),
                    'STATUS_REC' => 'A',
                    'LOAN_NUMBER' => $result->LOAN_NUMBER,
                    'START_DATE' => $result->PAYMENT_DATE,
                    'END_DATE' => null,
                    'PAST_DUE_PCPL' => $result->PRINCIPAL??0,
                    'PAST_DUE_INTRST' => $result->INTEREST??0,
                    'PAST_DUE_PENALTY' => $pastDuePenalty??0,
                    'PAID_PCPL' => 0,
                    'PAID_INT' => 0,
                    'PAID_PENALTY' => 0,
                    'CREATED_AT' => Carbon::now('Asia/Jakarta')
                ];
            }
    
            // Process arrears data
            foreach ($arrearsData as $data) {
                $existingArrears = M_Arrears::where([
                    'LOAN_NUMBER' => $data['LOAN_NUMBER'],
                    'START_DATE' => $data['START_DATE'],
                    'STATUS_REC' => 'A'
                ])->first();
    
                if ($existingArrears) {
                    // Update the existing record
                    $existingArrears->update([
                        'PAST_DUE_PENALTY' => $data['PAST_DUE_PENALTY']??0,
                        'UPDATED_AT' => Carbon::now('Asia/Jakarta')
                    ]);
                } else {
                    // Insert new record
                    M_Arrears::create($data);
                }
            }
    
            M_CronJobLog::create([
                'STATUS' => 'SUCCESS',
                'DESCRIPTION' => 'Records processed successfully'
            ]);
        } catch (\Exception $e) {
            M_CronJobLog::create([
                'STATUS' => 'FAIL',
                'DESCRIPTION' => $e->getMessage()
            ]);
        }
    }
    
}

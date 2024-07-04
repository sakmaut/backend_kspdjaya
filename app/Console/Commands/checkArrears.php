<?php

namespace App\Console\Commands;

use App\Models\M_Arrears;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class checkArrears extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-arrears';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if saldo is less than cicilan and insert to arrears table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $results = DB::table('credit_schedule as a')
                    ->join('credit as b', 'b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                    ->leftJoin('saving as c', 'c.ACC_NUM', '=', 'b.DEFAULT_ACCOUNT')
                    ->select(
                        'b.BRANCH',
                        'b.LOAN_NUMBER',
                        'b.INSTALLMENT as CICILAN', 
                        DB::raw('COALESCE(c.BALANCE, 0) AS SALDO'),
                        'a.PAYMENT_DATE',
                        'a.PRINCIPAL',
                        'a.INTEREST'
                    )
                    ->whereDate('a.PAYMENT_DATE', DB::raw('CURDATE()'))
                    ->get();

        foreach ($results as $result) {
            
            M_Arrears::create([
                'ID' => Uuid::uuid7()->toString(),
                'STATUS_REC' => 'A',
                'LOAN_NUMBER' => $result->LOAN_NUMBER,
                'START_DATE' => $result->PAYMENT_DATE??null,
                'END_DATE' =>null,
                'PAST_DUE_PCPL' => $result->PRINCIPAL??null,
                'PAST_DUE_INTRST' => $result->INTEREST??null,
                'PAST_DUE_PENALTY' => 0,
                'PAID_PCPL' => 0,
                'PAID_INT' => 0,
                'PAID_PENALTY' => 0,
                'WOFF_PCPL' => 0,
                'WOFF_INT' => 0,
                'WOFF_PENALTY' => 0,
                'PENALTY_RATE' => 0,
                'TRNS_CODE' => 0
            ]);
            
        }
    }
}

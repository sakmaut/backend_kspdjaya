<?php

namespace App\Console\Commands;

use App\Models\M_Credit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckingAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:checking-account';

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
        DB::beginTransaction();

        try {

            $credit = M_Credit::where('CREDIT_TYPE', 'rekening_koran')->where('STATUS_REC', 'AC')->get();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }
}

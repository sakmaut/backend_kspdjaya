<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\ListBanController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunListBanEveryTenSeconds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:listban-every-ten-seconds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        DB::beginTransaction();
        try {
            $results = DB::table('branch as a')
                                ->join('credit as b', 'b.BRANCH', '=', 'a.ID')
                                ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'b.CUST_CODE')
                                ->leftJoin('users as d', 'd.id', '=', 'b.MCF_ID')
                                ->leftJoin('cr_application as e', 'e.ORDER_NUMBER', '=', 'b.ORDER_NUMBER')
                                ->leftJoin('cr_survey as f', 'f.id', '=', 'e.CR_SURVEY_ID')
                                ->leftJoin('cr_collateral as g', 'g.CR_CREDIT_ID', '=', 'b.ID')
                            ->select(
                                'a.CODE',
                                'a.NAME as cabang',
                                'b.LOAN_NUMBER',
                                'c.NAME as customer_name',
                                'b.CREATED_AT',
                                'c.INS_ADDRESS',
                                'c.ZIP_CODE',
                                'c.PHONE_HOUSE',
                                'c.PHONE_PERSONAL',
                                'c.OCCUPATION',
                                'd.fullname',
                                'f.survey_note',
                                'b.PCPL_ORI',
                                'e.TOTAL_ADMIN',
                                'e.INSTALLMENT_TYPE',
                                'b.PERIOD',
                                DB::raw('DATEDIFF(b.FIRST_ARR_DATE, NOW()) as OVERDUE'),
                                DB::raw('99 as CYCLE'),
                                'b.STATUS_REC',
                                'b.PAID_PRINCIPAL',
                                'b.PAID_INTEREST',
                                DB::raw('b.PAID_PRINCIPAL + b.PAID_INTEREST as PAID_TOTAL'),
                                DB::raw('b.PCPL_ORI - b.PAID_PRINCIPAL as OUTSTANDING'),
                                'b.INSTALLMENT',
                                'b.INSTALLMENT_DATE',
                                'b.FIRST_ARR_DATE',
                                DB::raw("' ' as COLLECTOR"),
                                DB::raw('GROUP_CONCAT(CONCAT(g.BRAND, " ", g.TYPE)) as COLLATERAL'),
                                DB::raw('GROUP_CONCAT(g.POLICE_NUMBER) as POLICE_NUMBER'),
                                DB::raw('GROUP_CONCAT(g.ENGINE_NUMBER) as ENGINE_NUMBER'),
                                DB::raw('GROUP_CONCAT(g.CHASIS_NUMBER) as CHASIS_NUMBER'),
                                DB::raw('GROUP_CONCAT(g.PRODUCTION_YEAR) as PRODUCTION_YEAR'),
                                DB::raw('SUM(g.VALUE) as TOTAL_NILAI_JAMINAN'),
                                'b.CUST_CODE'
                            )
                            ->groupBy(
                                'a.CODE',
                                'a.NAME',
                                'b.LOAN_NUMBER',
                                'c.NAME',
                                'b.CREATED_AT',
                                'c.INS_ADDRESS',
                                'c.ZIP_CODE',
                                'c.PHONE_HOUSE',
                                'c.PHONE_PERSONAL',
                                'c.OCCUPATION',
                                'd.fullname',
                                'f.survey_note',
                                'b.PCPL_ORI',
                                'e.TOTAL_ADMIN',
                                'e.INSTALLMENT_TYPE',
                                'b.PERIOD',
                                DB::raw('DATEDIFF(b.FIRST_ARR_DATE, NOW())'),
                                'b.STATUS_REC',
                                'b.PAID_PRINCIPAL',
                                'b.PAID_INTEREST',
                                DB::raw('b.PAID_PRINCIPAL + b.PAID_INTEREST'),
                                DB::raw('b.PCPL_ORI - b.PAID_PRINCIPAL'),
                                'b.INSTALLMENT',
                                'b.INSTALLMENT_DATE',
                                'b.FIRST_ARR_DATE',
                                'b.CUST_CODE'
                            )
                            ->limit(5)
                            ->get();
    
                    $build = [];
                    foreach ($results as $result) {
                        $build[] =[
                                "KODE" => $result->CODE??'',
                                "CABANG" => $result->cabang??'',
                                "NO_KONTRAK" => $result->LOAN_NUMBER??'',
                                "NAMA_PELANGGAN" => $result->NAME??'',
                                "TGL_BOOKING" => isset($result->CREATED_AT) && !empty($result->CREATED_AT) ? date("d-m-Y", strtotime($result->CREATED_AT)) : '',
                                "ALAMAT_TAGIH" => $result->INS_ADDRESS??'',
                                "KODE_POS" => $result->ZIP_CODE??'',
                                "NO_TELP" => $result->PHONE_HOUSE??'',
                                "NO_HP" => $result->PHONE_PERSONAL??'',
                                "PEKERJAAN" => $result->OCCUPATION??'',
                                "SURVEYOR" => $result->fullname??'',
                                "CATT_SURVEY" => $result->survey_note??'',
                                "PKK_HUTANG" => $result->PCPL_ORI??'',
                                "JML_ANGS" => $result->PERIOD??'',
                                "PERIOD" => $result->INSTALLMENT_TYPE??'',
                                "OVERDUE" => $result->OVERDUE??'',
                                "CYCLE" => $result->CYCLE??'',
                                "STS_KONTRAK" => $result->STATUS_REC??'',
                                "OUTS_PKK_AKHIR" => $result->PAID_PRINCIPAL??'',
                                "OUTS_BNG_AKHIR" => $result->PAID_INTEREST??'',
                                "ANGSURAN" =>  $result->INSTALLMENT??'',
                                "JTH_TEMPO_AWAL" => date("d-m-Y",strtotime( $result->INSTALLMENT_DATE))??'',
                                "JTH_TEMPO_AKHIR" => date("d-m-Y",strtotime( $result->INSTALLMENT_DATE))??'',
                                "NAMA_BRG" =>  "SEPEDA MOTOR",
                                "TIPE_BRG" =>  $result->COLLATERAL??'',
                                "NO_POL" =>  $result->POLICE_NUMBER??'',
                                "NO_MESIN" =>  $result->ENGINE_NUMBER??'',
                                "NO_RANGKA" =>  $result->CHASIS_NUMBER??'',
                                "TAHUN" =>  $result->PRODUCTION_YEAR??'',
                                "NILAI_PINJAMAN" =>  $result->TOTAL_NILAI_JAMINAN??'',
                                "ADMIN" =>  $result->TOTAL_ADMIN??'',
                                "CUST_ID" =>  $result->CUST_CODE??'',
                         ] ;
                    }
    
                    if (!empty($build)) {
                        $filename = storage_path('logs/lisban/listban_' . Carbon::now('Asia/Jakarta')->format('Y-m-d') . '.txt');
            
                        file_put_contents($filename, json_encode($build, JSON_PRETTY_PRINT) . "\n");
                    }
                    
    
                DB::commit();
           } catch (\Throwable $e) {
                DB::rollback();
           }
    }
}

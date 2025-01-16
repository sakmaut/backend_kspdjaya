<?php

namespace App\Http\Controllers;

use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrPersonal;
use App\Models\M_CrProspect;
use App\Models\M_DeuteronomyTransactionLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

class Welcome extends Controller
{
    public function index(Request $request)
    {
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

                if (!empty($results)) {
                    $dataString = print_r($results, true);
                
                    $filename = storage_path('logs/lisban/listban_' . Carbon::now()->format('Y-m-d_H-i-s') . '.txt');
            
                    file_put_contents($filename, $dataString . "\n");
                }
                

            return response()->json('ok', 200);
       } catch (\Throwable $e) {
            return response()->json($e->getMessage(), 500);
       }
    }
   
}

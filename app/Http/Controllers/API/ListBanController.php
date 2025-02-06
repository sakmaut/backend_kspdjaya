<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListBanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $cabangId = $request->cabang_id;
            $getPosition = $request->user()->position;
            
            $datas = [
                'tgl_tarik' => $request->dari??'',
                'CASH_IN' => [],
                'CASH_OUT' => [],
            ];
            
            if (!empty($request->dari)) {
                $dateFrom = $request->dari;
                $arusKas = $this->queryArusKas($getPosition, $cabangId, $dateFrom);
            
                $no = 1;
                
                foreach ($arusKas as $item) {
                    // Handle 'CASH-IN'
                    if ($item->JENIS != 'PENCAIRAN') {
                        $found = false;
            
                        foreach ($datas['CASH_IN'] as &$data) {
                            // Check if a combination of no_invoice, no_kontrak, and nama_pelanggan exists
                            if ($data['no_invoice'] === $item->no_invoice
                                && $data['no_kontrak'] === $item->LOAN_NUM
                                && $data['nama_pelanggan'] === $item->PELANGGAN) {
                                
                                // Check if the angsuran_ke is not already in the keterangan
                                if (strpos($data['keterangan'], 'Angsuran Ke-' . $item->angsuran_ke) === false) {
                                    $data['metode_pembayaran'] .= ', ' . $item->PAYMENT_METHOD;
                                    $data['keterangan'] .= ', ' . $item->JENIS . ' Angsuran Ke-' . $item->angsuran_ke;
                                    $data['amount'] += floatval($item->ORIGINAL_AMOUNT);
                                }
                                
                                $found = true;
                                break;
                            }
                        }
            
                        $totalCashin = 0;
                        if (!$found) {
                            $datas['CASH_IN'][] = [
                                'no' => $no++,
                                'no_invoice' => $item->no_invoice ?? '',
                                'no_kontrak' => $item->LOAN_NUM?? '',
                                'cabang' => $item->nama_cabang??'',
                                'nama_pelanggan' => $item->PELANGGAN?? '',
                                'metode_pembayaran' => $item->PAYMENT_METHOD?? '',
                                'keterangan' => $item->JENIS . ' Angsuran Ke-' . $item->angsuran_ke ?? '',
                                'amount' => floatval($item->ORIGINAL_AMOUNT),
                            ];

                            $totalCashin += floatval($item->ORIGINAL_AMOUNT);
                        }

                        $datas['ttl_cash_in'] = $totalCashin;
                    }
            
                    $totalAmount = 0;

                    if ($item->JENIS == 'PENCAIRAN') {
                        $datas['CASH_OUT'][] = [
                            'no' => $no++,
                            'no_kontrak' => $item->LOAN_NUM ?? '',
                            'cabang' => $item->nama_cabang ?? '',
                            'nama_pelanggan' => $item->PELANGGAN ?? '',
                            'keterangan' => $item->LOAN_NUM ?? '',
                            'amount' => floatval($item->ORIGINAL_AMOUNT),
                        ];
                
                        $totalAmount += floatval($item->ORIGINAL_AMOUNT);
                    }
                    
                    $datas['ttl_cash_out'] = $totalAmount;
                }
            } else {
                $datas = [];
            }
            
            return response()->json($datas, 200);
            
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function queryArusKas($getPosition,$cabangId = null,$dateFrom) {

        $query = "  SELECT 
                        b.JENIS,
                        b.BRANCH,
                        b.BRANCH_ID,
                        b.ENTRY_DATE,
                        b.ORIGINAL_AMOUNT,
                        b.LOAN_NUM,
                        concat(b3.NAME,' (',b3.ALIAS,')') as PELANGGAN,
                        b.PAYMENT_METHOD,
                        b.nama_cabang,
                        b.no_invoice,
                        b.angsuran_ke
                    FROM (
                        SELECT 
                            a.ACC_KEYS as JENIS, 
                            b.BRANCH AS BRANCH, 
                            d.ID AS BRANCH_ID, 
                            d.NAME as nama_cabang,
                            b.ENTRY_DATE, 
                            a.ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE as no_invoice,
                            b.TITLE as angsuran_ke
                        FROM 
                            payment_detail a
                            INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                            LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                            LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH

                        UNION ALL

                        SELECT 
                            'PENCAIRAN' AS JENIS, 
                            b.CODE_NUMBER AS BRANCH,
                            b.ID AS BRANCH_ID, 
                            b.NAME as nama_cabang,
                            a.CREATED_AT AS ENTRY_DATE,
                            a.PCPL_ORI AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            'cash' as PAYMENT_METHOD,
                            '' as no_invoice,
                            '' as angsuran_ke
                        FROM 
                            credit a
                            INNER JOIN branch b ON b.id = a.BRANCH
                        WHERE 
                            a.STATUS = 'A'
                    ) AS b
                    INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
                    INNER JOIN customer b3 on b3.CUST_CODE = b2.CUST_CODE
                    WHERE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') = '$dateFrom' ";

            $params = [];

            if (strtolower($getPosition) != 'ho' && !empty($cabangId)) {
                $query .= empty($params) ? " WHERE" : " AND";
                $query .= " b.BRANCH_ID = :cabangId";
                $params['cabangId'] = $cabangId;
            }

            $result = DB::select($query, $params);

        return $result;
    }

    public function listBan(Request $request) {
        try {

            $getBranch = $request->user()->branch_id;
            $getPosition = $request->user()->position;

            $results = DB::table('branch as a')
                            ->join('credit as b', 'b.BRANCH', '=', 'a.ID')
                            ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'b.CUST_CODE')
                            ->leftJoin('users as d', 'd.id', '=', 'b.MCF_ID')
                            ->leftJoin('cr_application as e', 'e.ORDER_NUMBER', '=', 'b.ORDER_NUMBER')
                            ->leftJoin('cr_order as h', 'h.APPLICATION_ID', '=', 'e.ID')
                            ->leftJoin('cr_survey as f', 'f.id', '=', 'e.CR_SURVEY_ID')
                            ->leftJoin('cr_collateral as g', 'g.CR_CREDIT_ID', '=', 'b.ID')
                            ->select(
                                DB::raw("CONCAT(a.CODE, '-', a.CODE_NUMBER) as KODE"),
                                'a.NAME as NAMA_CABANG',
                                'b.LOAN_NUMBER as NO_KONTRAK',
                                'c.NAME as NAMA_PELANGGAN',
                                'b.CREATED_AT as TGL_BOOKING',
                                DB::raw('null as UB'),
                                DB::raw('null as PLATFORM'),
                                'c.INS_ADDRESS as ALAMAT_TAGIH',
                                'c.ZIP_CODE as KODE_POST',
                                'c.PHONE_HOUSE as NO_TELP',
                                'c.PHONE_PERSONAL as NO_HP',
                                'c.OCCUPATION as PEKERJAAN',
                                DB::raw('null as SURVEYOR'),
                                'f.survey_note as CATT_SURVEY',
                                'b.PCPL_ORI as PKK_HUTANG',
                                'e.INSTALLMENT_TYPE as tipe',
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
                                'b.CUST_CODE',
                                DB::raw("concat(h.REF_PELANGGAN,' ',h.REF_PELANGGAN_OTHER) as supplier")
                            )
                            // ->where('b.CREATED_AT', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 MONTH)'))
                            ->where('a.ID', $getBranch)
                            ->groupBy(
                                'a.CODE',
                                'a.CODE_NUMBER',
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
                                'b.CUST_CODE',
                                'h.REF_PELANGGAN',
                                'h.REF_PELANGGAN_OTHER'
                            )
                            ->get();


            $build = [];
            foreach ($results as $result) {
                $build[] =[
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" => $result->NO_KONTRAK ?? '',
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ? date("d-m-Y", strtotime($result->TGL_BOOKING)) : '',
                    "UB" => $result->UB ?? '',
                    "PLATFORM" => $result->PLATFORM ?? '',
                    "ALAMAT TAGIH" => $result->ALAMAT_TAGIH ?? '',
                    "KODEPOS" => $result->KODE_POST ?? '',
                    "SUBZIP" => '',
                    "NO TELP" => $result->NO_TELP ?? '',
                    "NO HP1" => $result->NO_HP ?? '',
                    "NO HP2" => $result->NO_HP ?? '',
                    "PEKERJAAN" => $result->PEKERJAAN ?? '',
                    "SUPPLIER" => $result->supplier??'',
                    "SURVEYOR" => $result->SURVEYOR ?? '',
                    "CATT SURVEY" => $result->CATT_SURVEY ?? '',
                    "PKK HUTANG" => number_format($result->PKK_HUTANG ?? 0),
                    "JML ANGS" => $result->PERIOD ?? '',
                    "JRK ANGS" => $result->PERIOD ?? '',
                    "PERIOD" => $result->tipe ?? '',
                    "OUT PKK AWAL" => '',
                    "OUT BNG AWAL" => '',
                    "OVERDUE AWAL" => number_format($result->OVERDUE ?? 0),
                    "AMBC PKK AWAL" => '',
                    "AMBC BNG AWAL" => '',
                    "AMBC TOTAL AWAL" => '',
                    "CYCLE AWAL" => $result->CYCLE ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => '',
                    "POLA BYR AWAL" => '',
                    "OUTS PKK AKHIR" => number_format($result->PAID_PRINCIPAL ?? 0),
                    "OUTS BNG AKHIR" => number_format($result->PAID_INTEREST ?? 0),
                    "OVERDUE AKHIR" => number_format($result->OUTSTANDING ?? 0),
                    "ANGSURAN" => number_format($result->INSTALLMENT ?? 0),
                    "ANGS KE" => '',
                    "TIPE ANGSURAN"=>'',
                    "JTH TEMPO AWAL" => date("d-m-Y", strtotime($result->INSTALLMENT_DATE ?? '')),
                    "JTH TEMPO AKHIR" => date("d-m-Y", strtotime($result->FIRST_ARR_DATE ?? '')),
                    "TGL BAYAR" => '',
                    "KOLEKTOR" => '',
                    "CARA BYR" => '',
                    "AMBC PKK_AKHIR" => '',
                    "AMBC BNG_AKHIR" => '',
                    "AMBC TOTAL_AKHIR" => '',
                    "AC PKK"=>'',
                    "AC BNG MRG"=>'',
                    "AC TOTAL"=>'',
                    "CYCLE AKHIR"=>'',
                    "POLA BYR AKHIR"=>'',
                    "NAMA BRG"=>'',
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" =>  $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => number_format($result->TOTAL_NILAI_JAMINAN ?? 0),
                    "ADMIN" =>  $result->TOTAL_ADMIN ?? '',
                    "CUST_ID" =>  $result->CUST_CODE??'',
                ] ;
            }
            return response()->json($build, 200);           
        }catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

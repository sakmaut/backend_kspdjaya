<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListBanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $datas = [
                'tgl_tarik' => $request->dari ?? '',
                'datas' => []
            ];

            if (!empty($request->dari)) {

                $timestamp = intval($request->dari) / 1000;
                $date = Carbon::createFromTimestamp($timestamp);

                // Format the date as Y-m-d
                $formattedDate = $date->format('Y-m-d');

                $dateFrom = $formattedDate;
                $cabangId = $request->cabang_id;

                $arusKas = $this->queryArusKas($cabangId, $dateFrom);

                $no = 1;
                $totalCashin = 0;
                $totalAmount = 0;

                $cash_in = [];

                $totalAngsuranPokokBunga = 0;
        
                foreach ($arusKas as $item) {

                    $row = $item->no_invoice . $item->LOAN_NUM . $item->PELANGGAN;

                    $no_invoice = $item->no_invoice;
                    $loan_num = $item->LOAN_NUM;
                    $pelanggan = $item->PELANGGAN;
                    $user = $item->fullname;

                    if (in_array($row, $cash_in)) {
                        $no_invoice = '';
                        $loan_num = '';
                        $pelanggan = '';
                    } else {
                        array_push($cash_in, $row);
                    }

                    $amount = is_numeric($item->ORIGINAL_AMOUNT) ? floatval($item->ORIGINAL_AMOUNT) : 0;

                    if ($item->JENIS != 'PENCAIRAN') {

                        if ($item->JENIS == 'ANGSURAN_POKOK' || $item->JENIS == 'ANGSURAN_BUNGA') {

                            $totalAngsuranPokokBunga += $amount;

                            $datas['datas'][] = [
                                'no' => $no++,
                                'type' => 'CASH_IN',
                                'no_invoice' => $no_invoice,
                                'no_kontrak' => $loan_num,
                                'tgl' => $item->ENTRY_DATE ?? '',
                                'cabang' => $item->nama_cabang ?? '',
                                'user' => $user ?? '',
                                'position' => $item->position ?? '',
                                'nama_pelanggan' => $pelanggan,
                                'metode_pembayaran' => $item->PAYMENT_METHOD ?? '',
                                'keterangan' => 'Bayar ' . $item->angsuran_ke ?? '',
                                'amount' => $totalAngsuranPokokBunga,
                            ];
                        } else {
                            $datas['datas'][] = [
                                'no' => $no++,
                                'type' => 'CASH_IN',
                                'no_invoice' => $no_invoice,
                                'no_kontrak' => $loan_num,
                                'tgl' => $item->ENTRY_DATE ?? '',
                                'cabang' => $item->nama_cabang ?? '',
                                'user' => $user ?? '',
                                'position' => $item->position ?? '',
                                'nama_pelanggan' => $pelanggan,
                                'metode_pembayaran' => $item->PAYMENT_METHOD ?? '',
                                'keterangan' => $item->JENIS . ' ' . $item->angsuran_ke ?? '',
                                'amount' => $amount,
                            ];
                        }

                        $totalCashin += floatval($item->ORIGINAL_AMOUNT);
                    }
                }

                foreach ($arusKas as $item) {
                    if ($item->JENIS == 'PENCAIRAN') {

                        $getTttl = floatval($item->ORIGINAL_AMOUNT) - floatval($item->admin_fee);

                        $datas['datas'][] = [
                            'no' => $no++,
                            'type' => 'CASH_OUT',
                            'no_kontrak' => $item->LOAN_NUM ?? '',
                            'tgl' => $item->ENTRY_DATE ?? '',
                            'cabang' => $item->nama_cabang ?? '',
                            'user' => $item->fullname ?? '',
                            'position' => $item->position ?? '',
                            'nama_pelanggan' => $item->PELANGGAN ?? '',
                            'keterangan' => 'PENCAIRAN NO KONTRAK ' . $item->LOAN_NUM ?? '',
                            'amount' => $getTttl,
                        ];

                        $totalAmount += $getTttl;
                    }
                }

                $datas['ttl_cash_in'] = $totalCashin;
                $datas['ttl_cash_out'] = $totalAmount;
                $datas['ttl_all'] = $totalCashin + $totalAmount;
            } else {
                $datas = [];
            }

            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function queryArusKas($cabangId, $dateFrom)
    {

        $query = "  SELECT 
                        b.JENIS,
                        b.BRANCH,
                        b.BRANCH_ID,
                        b.ENTRY_DATE,
                        b.ORIGINAL_AMOUNT,
                        b.LOAN_NUM,
                        CONCAT(b3.NAME, ' (', b3.ALIAS, ')') AS PELANGGAN,
                        b.PAYMENT_METHOD,
                        b.nama_cabang,
                        b.no_invoice,
                        b.angsuran_ke,
                        b.admin_fee,
                        b.user_id,
                        u.fullname,
                        u.position
                    FROM (
                        SELECT 
                            a.ACC_KEYS AS JENIS, 
                            b.BRANCH AS BRANCH, 
                            d.ID AS BRANCH_ID, 
                            d.NAME AS nama_cabang,
                            DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') AS ENTRY_DATE, 
                            a.ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE AS no_invoice,
                            b.TITLE AS angsuran_ke,
                            b.USER_ID AS user_id,
                            '' AS admin_fee
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
                            b.NAME AS nama_cabang,
                            DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') AS ENTRY_DATE,
                            a.PCPL_ORI AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            'cash' AS PAYMENT_METHOD,
                            '' AS no_invoice,
                            '' AS angsuran_ke,
                            a.CREATED_BY AS user_id,
                            a.TOTAL_ADMIN AS admin_fee
                        FROM 
                            credit a
                        INNER JOIN branch b ON b.id = a.BRANCH
                        WHERE 
                            a.STATUS = 'A'
                    ) AS b
                    INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
                    INNER JOIN customer b3 ON b3.CUST_CODE = b2.CUST_CODE
                    INNER JOIN users u ON u.id = b.user_id 
                    WHERE b.ENTRY_DATE = '$dateFrom'
                ";

        if (!empty($cabangId) && $cabangId != 'SEMUA CABANG') {
            $query .= " AND b.BRANCH_ID = '" . $cabangId . "'";
        }
        // Execute the query with parameters
        $result = DB::select($query);

        return $result;
    }

    public function listBan(Request $request)
    {
        try {

            $dateFrom = $request->dari;
            $getBranch = $request->cabang_id;

            $query = "  SELECT 
                            CONCAT(a.CODE, '-', a.CODE_NUMBER) AS KODE,
                            a.NAME AS NAMA_CABANG,
                            b.LOAN_NUMBER AS NO_KONTRAK,
                            c.NAME AS NAMA_PELANGGAN,
                            b.CREATED_AT AS TGL_BOOKING,
                            NULL AS UB,
                            NULL AS PLATFORM,
                            c.INS_ADDRESS AS ALAMAT_TAGIH,
                            c.ZIP_CODE AS KODE_POST,
                            '' AS SUB_ZIP,
                            c.PHONE_HOUSE AS NO_TELP,
                            c.PHONE_PERSONAL AS NO_HP,
                            c.PHONE_PERSONAL AS NO_HP2,
                            c.OCCUPATION AS PEKERJAAN,
                            CONCAT(h.REF_PELANGGAN, ' ', h.REF_PELANGGAN_OTHER) AS supplier, 
                            b.MCF_ID AS SURVEYOR,
                            f.survey_note AS CATT_SURVEY,
                            b.PCPL_ORI AS PKK_HUTANG,
                            b.PERIOD AS JUMLAH_ANGSURAN, 
                            b.INSTALLMENT_COUNT/b.PERIOD AS JARAK_ANGSURAN, 
                            b.INSTALLMENT_COUNT as PERIOD, 
                            coalesce(i.OS_POKOK,b.PCPL_ORI) AS OUTSTANDING,
                            coalesce(i.OS_BUNGA,b.INTRST_ORI) as OS_BUNGA, 
                            DATEDIFF(str_to_date('31012025','%d%m%Y'),i.TUNGGAKAN_PERTAMA) AS OVERDUE_AWAL,
                            coalesce(i.TUNGGAKAN_POKOK) as AMBC_PKK_AWAL, 
                            coalesce(i.TUNGGAKAN_BUNGA) as AMBC_BNG_AWAL, 
                            coalesce(i.TUNGGAKAN_POKOK)+coalesce(i.TUNGGAKAN_BUNGA) as AMBC_TOTAL_AWAL, 
                            concat('C',case when date_format(b.entry_date,'%m%Y')=date_format(now(),'%m%Y') then 'N'
		                                    when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then now() else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')=date_format(now(),'%m%Y') then '0'
		                                    when floor((DATEDIFF(str_to_date('01022025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0 then 'M' 
		                                    when ceil((DATEDIFF(str_to_date('01022025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)>=8 then 'X' 
                                            else ceil((DATEDIFF(str_to_date('01022025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30) end) AS CYCLE_AWAL,
                            b.STATUS_REC,
                            b.STATUS_REC, 
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar, 
                            b.PCPL_ORI-b.PAID_PRINCIPAL OS_PKK_AKHIR, 
                            coalesce(k.OS_BNG_AKHIR,0) as OS_BNG_AKHIR, 
                            j.DUE_DAYS as OVERDUE_AKHIR, 
                            b.INSTALLMENT,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.LAST_INST end as LAST_INST, 
                            e.INSTALLMENT_TYPE AS tipe,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end as F_ARR_CR_SCHEDL,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.F_ARR_CR_SCHEDL end as curr_arr, 
                            case when date_format(l.entry_date,'%m%Y')=date_format(now(),'%m%Y') then l.entry_date else null end as LAST_PAY, 
                            ' ' AS COLLECTOR,
                            l.payment_method as cara_bayar, 
                            coalesce(m.tggk_pkk,0) as AMBC_PKK_AKHIR, 
                            coalesce(m.tggk_bng,0) as AMBC_BNG_AKHIR, 
                            coalesce(m.tggk_pkk,0)+coalesce(m.tggk_bng,0) as AMBC_TOTAL_AKHIR, 
                            coalesce(m.byr_tggk_pkk,0) AC_PKK, 
                            coalesce(m.byr_tggk_bng,0) AC_BNG_MRG, 
                            coalesce(m.byr_tggk_pkk,0)+coalesce(m.byr_tggk_bng,0) AC_TOTAL, 
                            concat('C',case when floor(j.DUE_DAYS/30)>8 then 'X' else floor(j.DUE_DAYS/30) end) as CYCLE_AKHIR, 
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar_akhir, 
                            'jenis jaminan', 
                            g.COLLATERAL,
                            g.POLICE_NUMBER,
                            g.ENGINE_NUMBER,
                            g.CHASIS_NUMBER,
                            g.PRODUCTION_YEAR,
                            b.PCPL_ORI-b.TOTAL_ADMIN as TOTAL_PINJAMAN,
                            b.TOTAL_ADMIN, 
                            b.CUST_CODE
                        FROM  	branch AS a
                            INNER JOIN credit b ON b.BRANCH = a.ID AND b.STATUS='A' OR (b.BRANCH = a.ID AND b.STATUS in ('D','S') AND b.loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')=date_format(now(),'%m%Y')))
                            LEFT JOIN customer c ON c.CUST_CODE = b.CUST_CODE
                            LEFT JOIN users d ON d.id = b.MCF_ID
                            LEFT JOIN cr_application e ON e.ORDER_NUMBER = b.ORDER_NUMBER
                            LEFT JOIN cr_order h ON h.APPLICATION_ID = e.ID
                            LEFT JOIN cr_survey f ON f.id = e.CR_SURVEY_ID
                            LEFT JOIN (	SELECT	CR_CREDIT_ID, 
                                        sum(VALUE) as TOTAL_JAMINAN, 
                                        GROUP_CONCAT(concat(BRAND,' ',TYPE)) as COLLATERAL, 
                                        GROUP_CONCAT(POLICE_NUMBER) as POLICE_NUMBER, 
                                        GROUP_CONCAT(ENGINE_NUMBER) as ENGINE_NUMBER, 
                                        GROUP_CONCAT(CHASIS_NUMBER) as CHASIS_NUMBER, 
                                        GROUP_CONCAT(PRODUCTION_YEAR) as PRODUCTION_YEAR
                                    FROM 	cr_collateral 
                                    GROUP 	BY CR_CREDIT_ID) g ON g.CR_CREDIT_ID = b.ID
                                LEFT JOIN credit_2025 i on cast(i.loan_number as char) = cast(b.LOAN_NUMBER as char)
                                LEFT JOIN first_arr j on cast(j.LOAN_NUMBER as char) = cast(b.LOAN_NUMBER as char)

                            LEFT JOIN (	SELECT	loan_number, sum(interest)-sum(coalesce(payment_value_interest,0)) as OS_BNG_AKHIR, 
				                                case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        				                            else min(case when cast(paid_flag as char)='PAID' then 999 else installment_count end) end as LAST_INST, 
				                                max(case when cast(paid_flag as char)='PAID' then payment_date else str_to_date('01011900','%d%m%Y') end) as LAST_PAY, 
				                                case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        	 			                            else min(case when cast(coalesce(paid_flag,'') as char)<>'PAID' then payment_date else str_to_date('01013000','%d%m%Y') end) end as F_ARR_CR_SCHEDL
			                            FROM	credit_schedule
			                            WHERE	loan_number in (select loan_number from credit where status='A' 
					                            or (status in ('S','D') and loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')=date_format(now(),'%m%Y'))))
			                            GROUP	BY loan_number) k on k.loan_number=b.loan_number
                            LEFT JOIN (	SELECT	loan_num, entry_date, replace(replace(group_concat(payment_method),'AGENT EKS',''),',','') as payment_method
			                            FROM	payment
			                            WHERE	(cast(loan_num as char),date_format(entry_date,'%d%m%Y %H%i'),cast(title as char)) 
					                                in (select cast(loan_num as char), date_format(max(entry_date),'%d%m%Y %H%i'), concat('Angsuran Ke-',max(cast(replace(title,'Angsuran Ke-','') as signed))) from payment group by loan_num)
			                            group by loan_num, entry_date) l on l.loan_num=b.loan_number
                            LEFT JOIN (	SELECT	loan_number, 
                                        sum(past_due_pcpl) as tggk_pkk, sum(past_due_intrst) as tggk_bng, 
                                            sum(paid_pcpl) as byr_tggk_pkk, sum(paid_int) as byr_tggk_bng, 
                                        min(start_date) as curr_arr
                                    FROM	arrears
                                    WHERE	STATUS_REC='A'
                                    GROUP	BY loan_number) m on m.loan_number=b.loan_number
                            WHERE 1=1";

            if (!empty($getBranch) && $getBranch != 'SEMUA CABANG') {
                $query .= " AND a.ID = '$getBranch'";
            }

            $query .= " ORDER BY a.NAME, b.CREATED_AT ASC";

            $results = DB::select($query);

            $build = [];
            foreach ($results as $result) {

                $getUsers = User::find($result->SURVEYOR);

                $build[] = [
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" =>  (string)($result->NO_KONTRAK ?? ''),
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ? date("d-m-Y", strtotime($result->TGL_BOOKING)) : '',
                    "UB" => $result->UB ?? '',
                    "PLATFORM" => $result->PLATFORM ?? '',
                    "ALAMAT TAGIH" => $result->ALAMAT_TAGIH ?? '',
                    "KODE POS" => $result->KODE_POST ?? '',
                    "SUBZIP" => '',
                    "NO TELP" => $result->NO_TELP ?? '',
                    "NO HP1" => $result->NO_HP ?? '',
                    "NO HP2" => $result->NO_HP2 ?? '',
                    "PEKERJAAN" => $result->PEKERJAAN ?? '',
                    "SUPPLIER" => $result->supplier ?? '',
                    "SURVEYOR" => $getUsers ? $getUsers->fullname ?? '' : $result->SURVEYOR ?? '',
                    "CATT SURVEY" => $result->CATT_SURVEY ?? '',
                    "PKK HUTANG" => intval($result->PKK_HUTANG) ?? 0,
                    "JML ANGS" => $result->JUMLAH_ANGSURAN ?? '',
                    "JRK ANGS" => $result->JARAK_ANGSURAN ?? '',
                    "PERIOD" => $result->PERIOD ?? '',
                    "OUT PKK AWAL" => intval($result->OUTSTANDING) ?? 0,
                    "OUT BNG AWAL" => intval($result->OS_BUNGA) ?? 0,
                    "OVERDUE AWAL" => $result->OVERDUE_AWAL ?? 0,
                    "AMBC PKK AWAL" => $result->AMBC_PKK_AWAL,
                    "AMBC BNG AWAL" => $result->AMBC_BNG_AWAL,
                    "AMBC TOTAL AWAL" => $result->AMBC_TOTAL_AWAL,
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => $result->PAID_PRINCIPAL ?? 0,
                    "OUTS BNG AKHIR" => $result->PAID_INTEREST ?? 0,
                    "OVERDUE AKHIR" => $result->OUTSTANDING ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' ? '': date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY,
                    "KOLEKTOR" => $result->COLLECTOR,
                    "CARA BYR" => $result->cara_bayar,
                    "AMBC PKK_AKHIR" => intval($result->AMBC_PKK_AKHIR) ?? 0,
                    "AMBC BNG_AKHIR" => intval($result->AMBC_BNG_AKHIR) ?? 0,
                    "AMBC TOTAL_AKHIR" => intval($result->AMBC_TOTAL_AKHIR) ?? 0,
                    "AC PKK" => intval($result->AC_PKK),
                    "AC BNG MRG" => intval($result->AC_BNG_MRG),
                    "AC TOTAL" => intval($result->AC_TOTAL),
                    "CYCLE AKHIR" => $result->CYCLE_AKHIR,
                    "POLA BYR AKHIR" => $result->pola_bayar_akhir,
                    "NAMA BRG" => 'Sepeda Motor',
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" =>  $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => intval($result->TOTAL_PINJAMAN) ?? 0,
                    "ADMIN" =>  intval($result->TOTAL_ADMIN) ?? '',
                    "CUST_ID" =>  $result->CUST_CODE ?? ''
                ];
            }
            return response()->json($build, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

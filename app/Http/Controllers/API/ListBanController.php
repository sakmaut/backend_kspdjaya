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
                'dari' => $request->dari ?? '',
                'sampai' => $request->sampai ?? '',
                'datas' => []
            ];

            if (!empty($request->dari)) {
                $cabangId = $request->cabang_id;

                $arusKas = $this->queryArusKas($cabangId, $request);

                foreach ($arusKas as $item) {

                    $cabang = $item->nama_cabang;
                    $tgl = $item->ENTRY_DATE;
                    $user = $item->fullname;
                    $no_invoice = $item->no_invoice;
                    $loan_num = $item->LOAN_NUM;
                    $pelanggan = $item->PELANGGAN;
                    $position = $item->position;
                    $amount = is_numeric($item->ORIGINAL_AMOUNT) ? floatval($item->ORIGINAL_AMOUNT) : 0;

                    if ($item->JENIS != 'PENCAIRAN') {
                        if ($amount != 0) {

                            if ($item->angsuran_ke == 'PEMBULATAN') {
                                $keterangan = $item->angsuran_ke . ' (' . $item->no_invoice . ')';
                            } else {
                                $keterangan = 'BAYAR ' . $item->angsuran_ke . ' (' . $item->no_invoice . ')';
                            }

                            $datas['datas'][] = [
                                'type' => $item->JENIS == 'PELUNASAN' || $item->JENIS == 'PEMBULATAN PELUNASAN' || $item->JENIS == 'DENDA PELUNASAN' ? 'PELUNASAN' : 'CASH_IN',
                                'no_invoice' => $no_invoice,
                                'no_kontrak' => $loan_num,
                                'tgl' => $tgl ?? '',
                                'cabang' => $cabang ?? '',
                                'user' => $user ?? '',
                                'position' => $position ?? '',
                                'nama_pelanggan' => $pelanggan,
                                'metode_pembayaran' => $item->PAYMENT_METHOD ?? '',
                                'keterangan' => $keterangan,
                                'amount' => floatval($amount),
                            ];
                        }
                    }
                }

                foreach ($arusKas as $item) {
                    if ($item->JENIS == 'PENCAIRAN') {

                        $getTttl = floatval($item->ORIGINAL_AMOUNT) - floatval($item->admin_fee);

                        $datas['datas'][] = [
                            'type' => 'CASH_OUT',
                            'no_invoice' => '',
                            'no_kontrak' => $item->LOAN_NUM ?? '',
                            'tgl' => $item->ENTRY_DATE ?? '',
                            'cabang' => $item->nama_cabang ?? '',
                            'user' => $item->fullname ?? '',
                            'position' => $item->position ?? '',
                            'nama_pelanggan' => $item->PELANGGAN ?? '',
                            'metode_pembayaran' => '',
                            'keterangan' => 'PENCAIRAN NO KONTRAK ' . $item->LOAN_NUM ?? '',
                            'amount' => floatval(round($getTttl, 2)),

                        ];
                    }
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

    private function queryArusKas($cabangId, $request)
    {
        $query = "SELECT
                        b.JENIS,
                        b.BRANCH,
                        b.BRANCH_ID,
                        b.ENTRY_DATE,
                        b.ORIGINAL_AMOUNT,
                        b.LOAN_NUM,
                        b3.NAME AS PELANGGAN,
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
                            CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE
                            END AS JENIS,
                            b.BRANCH AS BRANCH,
                            d.ID AS BRANCH_ID,
                            d.NAME AS nama_cabang,
                            CASE
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
                            END AS ENTRY_DATE,
                            SUM(a.ORIGINAL_AMOUNT) AS ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE AS no_invoice,
                            CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE
                            END AS angsuran_ke,
                            b.USER_ID AS user_id,
                            '' AS admin_fee
                        FROM
                            payment_detail a
                        INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                        LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                        LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH
                        WHERE b.ACC_KEY in ('angsuran','angsuran_denda')
                              AND b.STTS_RCRD = 'PAID'
                              AND a.ACC_KEYS in ('BAYAR_POKOK','BAYAR_BUNGA','ANGSURAN_POKOK','ANGSURAN_BUNGA','BAYAR_DENDA')
                        GROUP BY
                            CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE
                            END,
                            b.BRANCH,
                            d.ID,
                            d.NAME,
                            CASE
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
                            END,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE,
                            CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE
                            END,
                            b.USER_ID
                        UNION ALL
                            SELECT
                             CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' OR a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'DENDA PELUNASAN'
                                ELSE 'PELUNASAN'
                            END AS JENIS,
                            b.BRANCH AS BRANCH,
                            d.ID AS BRANCH_ID,
                            d.NAME AS nama_cabang,
                            CASE
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
                            END AS ENTRY_DATE,
                            ROUND(SUM(a.ORIGINAL_AMOUNT),2) AS ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE AS no_invoice,
                            CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' OR a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'DENDA PELUNASAN'
                                ELSE 'PELUNASAN'
                            END AS angsuran_ke,
                            b.USER_ID AS user_id,
                            '' AS admin_fee
                        FROM
                            payment_detail a
                        INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                        LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                        LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH
                        WHERE b.ACC_KEY like '%Pelunasan%'
                              AND b.STTS_RCRD = 'PAID'
                              AND a.ACC_KEYS in ('BAYAR_POKOK','BAYAR_BUNGA','BAYAR_PINALTI','BAYAR PELUNASAN PINALTY','BAYAR_DENDA')
                        GROUP BY
                         	CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' OR a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'DENDA PELUNASAN'
                                ELSE 'PELUNASAN'
                            END,
                            b.BRANCH,
                            d.ID,
                            d.NAME,
                            CASE
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
                            END,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE,
                             CASE
                                WHEN a.ACC_KEYS LIKE '%DENDA%' OR a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'DENDA PELUNASAN'
                                ELSE 'PELUNASAN'
                            END,
                            b.USER_ID
                        UNION ALL
                        SELECT
                            CASE
                                WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
                                ELSE 'PEMBULATAN'
                            END AS JENIS,
                            d.CODE_NUMBER AS BRANCH,
                            d.ID AS BRANCH_ID,
                            d.NAME AS nama_cabang,
                            CASE
                                WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d')
                            END AS ENTRY_DATE,
                            a.PEMBULATAN AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            a.METODE_PEMBAYARAN,
                            a.NO_TRANSAKSI AS no_invoice,
                            'PEMBULATAN' AS angsuran_ke,
                            a.CREATED_BY AS user_id,
                            '' AS admin_fee
                        FROM kwitansi a
                        LEFT JOIN payment b ON b.INVOICE = a.NO_TRANSAKSI
                        LEFT JOIN branch d ON d.ID = a.BRANCH_CODE
                        WHERE a.STTS_PAYMENT = 'PAID'
                        GROUP BY
                            CASE
                                WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
                                ELSE 'PEMBULATAN'
                            END,
                            d.CODE_NUMBER,
                            d.ID,
                            d.NAME,
                            CASE
                                WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d')
                            END,
                            a.PEMBULATAN,
                            a.LOAN_NUMBER,
                            a.METODE_PEMBAYARAN,
                            a.NO_TRANSAKSI,
                            a.CREATED_BY
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
                        GROUP BY
                            b.CODE_NUMBER,
                            b.ID,
                            b.NAME,
                            DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d'),
                            a.PCPL_ORI,
                            a.LOAN_NUMBER,
                            a.CREATED_BY,
                            a.TOTAL_ADMIN
                    ) AS b
                    INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
                    INNER JOIN customer b3 ON b3.CUST_CODE = b2.CUST_CODE
                    INNER JOIN users u ON u.id = b.user_id
                    WHERE b.ENTRY_DATE BETWEEN '$request->dari' AND '$request->sampai' ";

        if (!empty($cabangId) && $cabangId != 'SEMUA CABANG') {
            $query .= " AND b.BRANCH_ID = '" . $cabangId . "'";
        }

        $query .= "ORDER BY b.ENTRY_DATE,u.position,b.LOAN_NUM,b.no_invoice,b.angsuran_ke ASC";

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
                            coalesce(d.fullname,b.mcf_id) AS SURVEYOR,
                            f.survey_note AS CATT_SURVEY,
                            b.PCPL_ORI AS PKK_HUTANG,
                            b.PERIOD AS JUMLAH_ANGSURAN,
                            b.INSTALLMENT_COUNT/b.PERIOD AS JARAK_ANGSURAN,
                            b.INSTALLMENT_COUNT as PERIOD,
                            coalesce(i.OS_POKOK,b.PCPL_ORI) AS OUTSTANDING,
                            coalesce(i.OS_BUNGA,b.INTRST_ORI) as OS_BUNGA,
                            DATEDIFF(str_to_date('28022025','%d%m%Y'),i.TUNGGAKAN_PERTAMA) AS OVERDUE_AWAL,
                            coalesce(i.TUNGGAKAN_POKOK) as AMBC_PKK_AWAL,
                            coalesce(i.TUNGGAKAN_BUNGA) as AMBC_BNG_AWAL,
                            coalesce(i.TUNGGAKAN_POKOK)+coalesce(i.TUNGGAKAN_BUNGA) as AMBC_TOTAL_AWAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')=date_format(now(),'%m%Y') then 'N'
		                                    when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then now() else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')=date_format(now(),'%m%Y') then '0'
		                                    when floor((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0 then 'M'
                                            when (DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then now() else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end)) between 211 and 240 then '8'
		                                    when ceil((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)>=8 then 'X'
                                            else ceil((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30) end) AS CYCLE_AWAL,
                            b.STATUS_REC,
                            b.STATUS_REC,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar,
                            b.PCPL_ORI-b.PAID_PRINCIPAL OS_PKK_AKHIR,
                            coalesce(k.OS_BNG_AKHIR,0) as OS_BNG_AKHIR,
                            j.DUE_DAYS as OVERDUE_AKHIR,
                            b.INSTALLMENT,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else i.INST_COUNT end as LAST_INST,
                            e.INSTALLMENT_TYPE AS tipe,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end as F_ARR_CR_SCHEDL,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.F_ARR_CR_SCHEDL end as curr_arr,
                            case when date_format(l.entry_date,'%m%Y')=date_format(now(),'%m%Y') then l.entry_date else null end as LAST_PAY,
                            ' ' AS COLLECTOR,
                            l.payment_method as cara_bayar,
                            coalesce(k.AMBC_PKK_AKHIR,0) as AMBC_PKK_AKHIR,
                            coalesce(k.AMBC_BNG_AKHIR,0) as AMBC_BNG_AKHIR,
                            coalesce(k.AMBC_PKK_AKHIR,0)+coalesce(k.AMBC_BNG_AKHIR,0) as AMBC_TOTAL_AKHIR,
                            coalesce(m.BAYAR_POKOK,0) AC_PKK,
                            coalesce(m.BAYAR_BUNGA,0) AC_BNG_MRG,
                            coalesce(m.BAYAR_POKOK,0)+coalesce(m.BAYAR_BUNGA,0) AC_TOTAL,
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
                                                        and i.back_date='2025-02-28'
                                LEFT JOIN first_arr j on cast(j.LOAN_NUMBER as char) = cast(b.LOAN_NUMBER as char)

                            LEFT JOIN (	SELECT	loan_number, sum(interest)-sum(coalesce(payment_value_interest,0))-sum(discount_interest) as OS_BNG_AKHIR,
				                                sum(principal)-sum(coalesce(payment_value_principal,0))-sum(discount_principal) as OS_PKK_AKHIR,
				case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        				else min(case when cast(paid_flag as char)='PAID' then 999 else installment_count end) end as LAST_INST,
				max(case when cast(paid_flag as char)='PAID' then payment_date else str_to_date('01011900','%d%m%Y') end) as LAST_PAY,
				case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        	 			else min(case when cast(coalesce(paid_flag,'') as char)<>'PAID' then payment_date else str_to_date('01013000','%d%m%Y') end) end as F_ARR_CR_SCHEDL,
				sum(case when payment_date < str_to_date(concat('01',date_format(date_add(now(),interval 1 month),'%m%Y')),'%d%m%Y') and paid_flag<>'PAID' then (interest-payment_value_interest-discount_interest)
            	 			else 0 end) as AMBC_BNG_AKHIR,
				sum(case when payment_date < str_to_date(concat('01',date_format(date_add(now(),interval 1 month),'%m%Y')),'%d%m%Y') and paid_flag<>'PAID' then (principal-payment_value_principal-discount_principal)
            	 			else 0 end) as AMBC_PKK_AKHIR
			FROM	credit_schedule
			WHERE	loan_number in (select loan_number from credit where status='A'
					or (status in ('S','D') and loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')=date_format(now(),'%m%Y'))))
			GROUP	BY loan_number) k on k.loan_number=b.loan_number
                            LEFT JOIN (	SELECT	loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y') as entry_date,
                                                replace(replace(group_concat(payment_method),'AGENT EKS',''),',','') as payment_method,
                                                concat('Angsuran Ke-',max(cast(replace(title,'Angsuran Ke-','') as signed)))
			                            FROM	payment
			                            WHERE	(cast(loan_num as char),date_format(entry_date,'%d%m%Y %H%i')) in
                                        (select cast(s1.loan_num as char), date_format(max(s1.entry_date),'%d%m%Y %H%i')
         				                 from 	payment s1
         					                    inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
         				                 group 	by s1.loan_num)
			                            group by loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y')) l on l.loan_num=b.loan_number
                            LEFT JOIN (	SELECT	s1.LOAN_NUM,
				sum(case when s2.ACC_KEYS in ('BAYAR_POKOK','ANGSURAN_POKOK') then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_POKOK,
        			sum(case when s2.ACC_KEYS='ANGSURAN_BUNGA' then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_BUNGA
			FROM	payment s1
				inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID
			WHERE	date_format(s1.ENTRY_DATE,'%m%Y')=date_format(now(),'%m%Y')
                    and s2.ACC_KEYS in ('BAYAR_POKOK','ANGSURAN_POKOK','ANGSURAN_BUNGA')
			GROUP	BY s1.LOAN_NUM) m on m.loan_num=b.loan_number
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
                    "AMBC PKK AWAL" => intval($result->AMBC_PKK_AWAL),
                    "AMBC BNG AWAL" => intval($result->AMBC_BNG_AWAL),
                    "AMBC TOTAL AWAL" => intval($result->AMBC_TOTAL_AWAL),
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => $result->PAID_PRINCIPAL ?? 0,
                    "OUTS BNG AKHIR" => $result->PAID_INTEREST ?? 0,
                    "OVERDUE AKHIR" => intval($result->OUTSTANDING) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' : date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : date("d-m-Y", strtotime($result->LAST_PAY ?? '')),
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

    public function listBanTestOLD(Request $request)
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
                            coalesce(d.fullname,b.mcf_id) AS SURVEYOR,
                            f.survey_note AS CATT_SURVEY,
                            replace(format(b.PCPL_ORI,0),',','') AS PKK_HUTANG,
                            b.PERIOD AS JUMLAH_ANGSURAN,
                            replace(format(b.INSTALLMENT_COUNT/b.PERIOD,0),',','') AS JARAK_ANGSURAN,
                            b.INSTALLMENT_COUNT as PERIOD,
                            replace(format(coalesce(i.OS_POKOK,b.PCPL_ORI),0),',','') AS OUTSTANDING,
                            replace(format(coalesce(i.OS_BUNGA,b.INTRST_ORI),0),',','') AS OS_BUNGA,
                            case when DATEDIFF(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),i.TUNGGAKAN_PERTAMA)<0 then 0
                                else DATEDIFF(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),i.TUNGGAKAN_PERTAMA) end AS OVERDUE_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_POKOK),0),',','') as AMBC_PKK_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_BUNGA),0),',','') as AMBC_BNG_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_POKOK)+coalesce(i.TUNGGAKAN_BUNGA),0),',','') as AMBC_TOTAL_AWAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')='$dateFrom' then 'N'
                                when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')='$dateFrom'
                                    then '0'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0
                                    then 'M'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)>=8
                                    then 'X'
                                when (DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end)) between 211 and 240
                                    then '8'
                                else floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30) end) AS CYCLE_AWAL,
                            b.STATUS_REC,
                            b.STATUS_REC,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                            replace(format(b.PCPL_ORI-b.PAID_PRINCIPAL,0),',','') OS_PKK_AKHIR,
                            replace(format(coalesce(k.OS_BNG_AKHIR,0),0),',','') as OS_BNG_AKHIR,
                            j.DUE_DAYS as OVERDUE_AKHIR,
                            b.INSTALLMENT,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.LAST_INST end as LAST_INST,
                            e.INSTALLMENT_TYPE AS tipe,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end as F_ARR_CR_SCHEDL,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.F_ARR_CR_SCHEDL end as curr_arr,
                            case when date_format(l.entry_date,'%m%Y')='$dateFrom' then l.entry_date else null end as LAST_PAY,
                            ' ' AS COLLECTOR,
                            l.payment_method as cara_bayar,
                            replace(format(coalesce(k.AMBC_PKK_AKHIR,0),0),',','') as AMBC_PKK_AKHIR,
                            replace(format(coalesce(k.AMBC_BNG_AKHIR,0),0),',','') as AMBC_BNG_AKHIR,
                            replace(format(coalesce(k.AMBC_PKK_AKHIR,0)+coalesce(k.AMBC_BNG_AKHIR,0),0),',','') as AMBC_TOTAL_AKHIR,
                            replace(format(coalesce(m.BAYAR_POKOK,0),0),',','') AC_PKK,
                            replace(format(coalesce(m.BAYAR_BUNGA,0),0),',','') AC_BNG_MRG,
                            replace(format(coalesce(m.BAYAR_POKOK,0)+coalesce(m.BAYAR_BUNGA,0),0),',','') AC_TOTAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')='$dateFrom' then 'N'
                                when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')='$dateFrom'
                                    then '0'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0
                                    then 'M'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else k.F_ARR_CR_SCHEDL end))/30)>=8
                                    then 'X'
                                when (DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else k.F_ARR_CR_SCHEDL end)) between 211 and 240
                                    then '8'
                                else floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then 0 else k.F_ARR_CR_SCHEDL end))/30) end) AS CYCLE_AKHIR,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar_akhir,
                            'jenis jaminan',
                            g.COLLATERAL,
                            g.POLICE_NUMBER,
                            g.ENGINE_NUMBER,
                            g.CHASIS_NUMBER,
                            g.PRODUCTION_YEAR,
                            replace(format(b.PCPL_ORI-b.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                            replace(format(b.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                            b.CUST_CODE
                        FROM  	branch AS a
                                INNER JOIN credit_log_2025 b
                                    ON b.BRANCH = a.ID
                                    AND b.STATUS='A'
                                    OR (b.BRANCH = a.ID
                                        AND b.STATUS in ('D','S')
                                        AND b.loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')='$dateFrom'))
                                LEFT JOIN customer c ON c.CUST_CODE = b.CUST_CODE
                                LEFT JOIN users d ON d.id = b.MCF_ID
                                LEFT JOIN cr_application e ON e.ORDER_NUMBER = b.ORDER_NUMBER
                                LEFT JOIN cr_order h ON h.APPLICATION_ID = e.ID
                                LEFT JOIN cr_survey f ON f.id = e.CR_SURVEY_ID	LEFT JOIN (	SELECT	CR_CREDIT_ID,
                                                                                                    sum(VALUE) as TOTAL_JAMINAN,
                                                                                                    GROUP_CONCAT(concat(BRAND,' ',TYPE)) as COLLATERAL,
                                                                                                    GROUP_CONCAT(POLICE_NUMBER) as POLICE_NUMBER,
                                                                                                    GROUP_CONCAT(ENGINE_NUMBER) as ENGINE_NUMBER,
                                                                                                    GROUP_CONCAT(CHASIS_NUMBER) as CHASIS_NUMBER,
                                                                                                    GROUP_CONCAT(PRODUCTION_YEAR) as PRODUCTION_YEAR
                                                                                            FROM 	cr_collateral
                                                                                            GROUP 	BY CR_CREDIT_ID) g ON g.CR_CREDIT_ID = b.ID
                                LEFT JOIN credit_2025 i
                                    on cast(i.loan_number as char) = cast(b.LOAN_NUMBER as char)
                                    and i.back_date = date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day)
                                LEFT JOIN first_arr j on cast(j.LOAN_NUMBER as char) = cast(b.LOAN_NUMBER as char)
                                LEFT JOIN (	SELECT	loan_number, sum(interest)-sum(coalesce(payment_value_interest,0))-sum(discount_interest) as OS_BNG_AKHIR,
                                                    sum(principal)-sum(coalesce(payment_value_principal,0))-sum(discount_principal) as OS_PKK_AKHIR,
                                                    case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
                                                            else min(case when cast(paid_flag as char)='PAID' then 999 else installment_count end) end as LAST_INST,
                                                    max(case when cast(paid_flag as char)='PAID' then payment_date else str_to_date('01011900','%d%m%Y') end) as LAST_PAY,
                                                    case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
                                                            else min(case when cast(coalesce(paid_flag,'') as char)<>'PAID' then payment_date else str_to_date('01013000','%d%m%Y') end) end as F_ARR_CR_SCHEDL,
                                                    sum(case when payment_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month) and paid_flag<>'PAID' then (interest-payment_value_interest-discount_interest)
                                                                else 0 end) as AMBC_BNG_AKHIR,
                                                    sum(case when payment_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month) and paid_flag<>'PAID' then (principal-payment_value_principal-discount_principal)
                                                                else 0 end) as AMBC_PKK_AKHIR
                                            FROM	credit_schedule_log_2025
                                            WHERE	back_date = date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                    and loan_number in (select 	loan_number
                                                                        from 	credit_log_2025
                                                                        where 	back_date = date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                                                and status='A'
                                                                                or (status in ('S','D') and loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')='$dateFrom')))
                                                                        GROUP	BY loan_number) k on k.loan_number=b.loan_number
                                LEFT JOIN (	SELECT	loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y') as entry_date,
                                                    replace(replace(group_concat(payment_method),'AGENT EKS',''),',','') as payment_method
                                            FROM	payment
                                            WHERE	(cast(loan_num as char),date_format(entry_date,'%d%m%Y %H%i'),cast(title as char)) in
                                                    (select cast(s1.loan_num as char), date_format(max(s1.entry_date),'%d%m%Y %H%i'), concat('Angsuran Ke-',max(cast(replace(s1.title,'Angsuran Ke-','') as signed)))
                                                    from 	payment s1
                                                            inner join payment_detail s2
                                                                on s2.PAYMENT_ID=s1.ID
                                                                and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
                                                    group 	by s1.loan_num)
                                                    and entry_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month)
                                        group by loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y')) l on l.loan_num=b.loan_number
                                LEFT JOIN (	SELECT	s1.LOAN_NUM,
                                                    sum(case when s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK') then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_POKOK,
                                                    sum(case when s2.ACC_KEYS='ANGSURAN_BUNGA' then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_BUNGA
                                            FROM	payment s1
                                                    inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID
                                            WHERE	date_format(s1.ENTRY_DATE,'%m%Y')='$dateFrom'
                                                    and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
                                            GROUP	BY s1.LOAN_NUM) m on m.loan_num=b.loan_number
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
                    "AMBC PKK AWAL" => intval($result->AMBC_PKK_AWAL),
                    "AMBC BNG AWAL" => intval($result->AMBC_BNG_AWAL),
                    "AMBC TOTAL AWAL" => intval($result->AMBC_TOTAL_AWAL),
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => intval($result->OS_PKK_AKHIR) ?? 0,
                    "OUTS BNG AKHIR" => intval($result->OS_BNG_AKHIR) ?? 0,
                    "OVERDUE AKHIR" => intval($result->OUTSTANDING) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' : date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : date("d-m-Y", strtotime($result->LAST_PAY ?? '')),
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
                    "NILAI PINJAMAN" => intval($result->NILAI_PINJAMAN) ?? 0,
                    "ADMIN" =>  intval($result->TOTAL_ADMIN) ?? '',
                    "CUST_ID" =>  $result->CUST_CODE ?? ''
                ];
            }
            return response()->json($build, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function listBanTest(Request $request)
    {

        try {
            $dateFrom = $request->dari;
            $getBranch = $request->cabang_id;
            $getPosition = $request->user()->position;

            $getBranchIdUser = $request->user()->branch_id;

            $query1 = "SELECT  CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.NAME AS NAMA_CABANG,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                c.NAME AS NAMA_PELANGGAN,
                                cl.CREATED_AT AS TGL_BOOKING,
                                NULL AS UB,
                                NULL AS PLATFORM,
                                c.INS_ADDRESS AS ALAMAT_TAGIH,
                                c.INS_KECAMATAN AS KODE_POST,
                                c.INS_KELURAHAN AS SUB_ZIP,
                                c.PHONE_HOUSE AS NO_TELP,
                                c.PHONE_PERSONAL AS NO_HP,
                                c.PHONE_PERSONAL AS NO_HP2,
                                c.OCCUPATION AS PEKERJAAN,
                                CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS supplier,
                                coalesce(u.fullname,cl.mcf_id) AS SURVEYOR,
                                cs.survey_note AS CATT_SURVEY,
                                replace(format(cl.PCPL_ORI ,0),',','') AS PKK_HUTANG,
                                cl.PERIOD AS JUMLAH_ANGSURAN,
                                replace(format(cl.PERIOD/cl.INSTALLMENT_COUNT,0),',','') AS JARAK_ANGSURAN,
                                cl.INSTALLMENT_COUNT as PERIOD,
		                        replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then cl.PCPL_ORI
			 			                        else st.init_pcpl end,0),',','') AS OUTSTANDING,
		                        replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then cl.INTRST_ORI
			 			                        else st.init_int end,0),',','') AS OS_BUNGA,
                                case when coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) < 0 then 0
                                    else coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) end as OVERDUE_AWAL,
                                replace(format(coalesce(st.arr_pcpl,0),0),',','') as AMBC_PKK_AWAL,
                                replace(format(coalesce(st.arr_int,0),0),',','') as AMBC_BNG_AWAL,
                                replace(format((coalesce(st.arr_pcpl,0)+coalesce(st.arr_int,0)),0),',','') as AMBC_TOTAL_AWAL,
                                concat('C',case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then 'N'
                                                when replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (st.init_pcpl+st.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(st.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),'%m%Y') then 'N'
                                                when st.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month) then 'N'
                                                when st.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when st.arr_count > 8 then 'X'
                                                else st.arr_count end) AS CYCLE_AWAL,
                                cl.STATUS_REC,
                                cl.STATUS_REC as STATUS_BEBAN,
                                case when (cl.PERIOD/cl.INSTALLMENT_COUNT)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                                replace(format(coalesce(en.init_pcpl,0),0),',','') OS_PKK_AKHIR,
                                replace(format(coalesce(en.init_int,0),0),',','') as OS_BNG_AKHIR,
                                case when coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) < 0 then 0
                                    else coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) end as OVERDUE_AKHIR,
                                cl.INSTALLMENT,
                                case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then 1
                                			 when coalesce(st.first_arr,en.first_arr) is null then ''
                                			 else coalesce(st.last_inst,en.last_inst) end as LAST_INST,
                                ca.INSTALLMENT_TYPE AS tipe,
                                case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end as F_ARR_CR_SCHEDL,
                                en.first_arr as curr_arr,
                                py.last_pay  as LAST_PAY,
                                k.kolektor AS COLLECTOR,
                                py.payment_method as cara_bayar,
                                replace(format(coalesce(en.arr_pcpl,0),0),',','') as AMBC_PKK_AKHIR,
                                replace(format(coalesce(en.arr_int ,0),0),',','') as AMBC_BNG_AKHIR,
                                replace(format(coalesce(en.arr_pcpl,0)+coalesce(en.arr_int,0),0),',','') as AMBC_TOTAL_AKHIR,
                                replace(format(coalesce(py.this_pcpl,0),0),',','') AC_PKK,
                                replace(format(coalesce(py.this_int,0),0),',','') AC_BNG_MRG,
                                replace(format(coalesce(py.this_cash,0),0),',','') AC_TOTAL,
                                concat('C',case when cl.STATUS <> 'A' then 'L'
                                                when replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (en.init_pcpl+en.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(en.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),'%m%Y') then 'N'
                                                when en.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 3 month) then 'N'
                                                when en.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when en.arr_count > 8 then 'X'
                                                else en.arr_count end) AS CYCLE_AKHIR,
                                case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar_akhir,
                                ' 'as jenis_jaminan,
                                col.COLLATERAL,
                                col.POLICE_NUMBER,
                                col.ENGINE_NUMBER,
                                col.CHASIS_NUMBER,
                                col.PRODUCTION_YEAR,
                                replace(format(cl.PCPL_ORI-cl.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                                replace(format(cl.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                                cl.CUST_CODE
                        FROM	credit_log_2025 cl
                                inner join branch b on cast(b.ID as char) = cast(cl.BRANCH as char)
                                left join customer c on cast(c.CUST_CODE as char) = cast(cl.CUST_CODE as char)
                                left join users u on cast(u.ID as char) = cast(cl.MCF_ID as char)
                                left join cr_application ca on cast(ca.ORDER_NUMBER as char) = cast(cl.ORDER_NUMBER as char)
                                left join cr_order co on cast(co.APPLICATION_ID as char) = cast(ca.ID as char)
                                left join cr_survey cs on cast(cs.ID as char) = cast(ca.CR_SURVEY_ID as char)
                                left join kolektor k on cast(k.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                left join temp_lis_03 col on cast(col.CR_CREDIT_ID as char) = cast(cl.ID as char)
                                left join temp_lis_01 st
                                    on cast(st.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and st.type=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),'%d%m%Y')
                                left join temp_lis_01 en
                                    on cast(en.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and en.type=date_format(date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day),'%d%m%Y')
                                left join temp_lis_02 py on cast(py.loan_num as char) = cast(cl.LOAN_NUMBER as char)
                        WHERE	date_format(cl.BACK_DATE,'%d%m%Y')=date_format(date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day),'%d%m%Y')
                                and (cl.STATUS = 'A' or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char)from temp_lis_02 )))";

            $query2 = "SELECT	CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.NAME AS NAMA_CABANG,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                c.NAME AS NAMA_PELANGGAN,
                                cl.CREATED_AT AS TGL_BOOKING,
                                NULL AS UB,
                                NULL AS PLATFORM,
                                c.INS_ADDRESS AS ALAMAT_TAGIH,
                                c.INS_KECAMATAN AS KODE_POST,
                                c.INS_KELURAHAN AS SUB_ZIP,
                                c.PHONE_HOUSE AS NO_TELP,
                                c.PHONE_PERSONAL AS NO_HP,
                                c.PHONE_PERSONAL AS NO_HP2,
                                c.OCCUPATION AS PEKERJAAN,
                                CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS supplier,
                                coalesce(u.fullname,cl.mcf_id) AS SURVEYOR,
                                cs.survey_note AS CATT_SURVEY,
                                replace(format(cl.PCPL_ORI ,0),',','') AS PKK_HUTANG,
                                cl.PERIOD AS JUMLAH_ANGSURAN,
                                replace(format(cl.PERIOD/cl.INSTALLMENT_COUNT,0),',','') AS JARAK_ANGSURAN,
                                cl.INSTALLMENT_COUNT as PERIOD,
                                replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then cl.PCPL_ORI
			 			                        else st.init_pcpl end,0),',','') AS OUTSTANDING,
		                        replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then cl.INTRST_ORI
			 			                        else st.init_int end,0),',','') AS OS_BUNGA,
                                case when coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) < 0 then 0
                                    else coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) end as OVERDUE_AWAL,
                                replace(format(coalesce(st.arr_pcpl,0),0),',','') as AMBC_PKK_AWAL,
                                replace(format(coalesce(st.arr_int,0),0),',','') as AMBC_BNG_AWAL,
                                replace(format((coalesce(st.arr_pcpl,0)+coalesce(st.arr_int,0)),0),',','') as AMBC_TOTAL_AWAL,
                                concat('C',case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then 'N'
                                                when replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (st.init_pcpl+st.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(st.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),'%m%Y') then 'N'
                                                when st.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month) then 'N'
                                                when st.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when st.arr_count > 8 then 'X'
                                                else st.arr_count end) AS CYCLE_AWAL,
                                cl.STATUS_REC,
                                cl.STATUS_REC as STATUS_BEBAN,
                                case when (cl.PERIOD/cl.INSTALLMENT_COUNT)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                                replace(format(coalesce(en.init_pcpl,0),0),',','') OS_PKK_AKHIR,
                                replace(format(coalesce(en.init_int,0),0),',','') as OS_BNG_AKHIR,
                                case when coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) < 0 then 0
                                    else coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) end as OVERDUE_AKHIR,
                                cl.INSTALLMENT,
                                case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then 1
                                			 when coalesce(st.first_arr,en.first_arr) is null then ''
                                			 else coalesce(st.last_inst,en.last_inst) end as LAST_INST,
                                ca.INSTALLMENT_TYPE AS tipe,
                                case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end as F_ARR_CR_SCHEDL,
                                en.first_arr as curr_arr,
                                py.last_pay  as LAST_PAY,
                                k.kolektor AS COLLECTOR,
                                py.payment_method as cara_bayar,
                                replace(format(coalesce(en.arr_pcpl,0),0),',','') as AMBC_PKK_AKHIR,
                                replace(format(coalesce(en.arr_int ,0),0),',','') as AMBC_BNG_AKHIR,
                                replace(format(coalesce(en.arr_pcpl,0)+coalesce(en.arr_int,0),0),',','') as AMBC_TOTAL_AKHIR,
                                replace(format(coalesce(py.this_pcpl,0),0),',','') AC_PKK,
                                replace(format(coalesce(py.this_int,0),0),',','') AC_BNG_MRG,
                                replace(format(coalesce(py.this_cash,0),0),',','') AC_TOTAL,
                                concat('C',case when cl.STATUS <> 'A' then 'L'
                                                when replace(format(case when date_format(cl.entry_date,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (en.init_pcpl+en.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(en.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),'%m%Y') then 'N'
                                                when en.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 3 month) then 'N'
                                                when en.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when en.arr_count > 8 then 'X'
                                                else en.arr_count end) AS CYCLE_AKHIR,
                                case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar_akhir,
                                ' 'as jenis_jaminan,
                                col.COLLATERAL,
                                col.POLICE_NUMBER,
                                col.ENGINE_NUMBER,
                                col.CHASIS_NUMBER,
                                col.PRODUCTION_YEAR,
                                replace(format(cl.PCPL_ORI-cl.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                                replace(format(cl.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                                cl.CUST_CODE
                        FROM	credit cl
                                inner join branch b on cast(b.ID as char) = cast(cl.BRANCH as char)
                                left join customer c on cast(c.CUST_CODE as char) = cast(cl.CUST_CODE as char)
                                left join users u on cast(u.ID as char) = cast(cl.MCF_ID as char)
                                left join cr_application ca on cast(ca.ORDER_NUMBER as char) = cast(cl.ORDER_NUMBER as char)
                                left join cr_order co on cast(co.APPLICATION_ID as char) = cast(ca.ID as char)
                                left join cr_survey cs on cast(cs.ID as char) = cast(ca.CR_SURVEY_ID as char)
                                left join kolektor k on cast(k.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                left join temp_lis_03C col on cast(col.CR_CREDIT_ID as char) = cast(cl.ID as char)
                                left join temp_lis_01C st
                                    on cast(st.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and st.type=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),'%d%m%Y')
                                left join temp_lis_01C en
                                    on cast(en.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and en.type=date_format(now(),'%d%m%Y')
                                left join temp_lis_02C py on cast(py.loan_num as char) = cast(cl.LOAN_NUMBER as char)
                        WHERE	(cl.STATUS = 'A'  or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char) from temp_lis_02C )))";

            $getNow = date('mY', strtotime(now()));

            if ($getNow == $dateFrom) {

                $checkRunSp = DB::select(" SELECT
                                                CASE
                                                    WHEN (SELECT MAX(p.ENTRY_DATE) FROM payment p) > (SELECT MAX(temp_lis_02C.last_pay) FROM temp_lis_02C)
                                                        AND job_status = 0 THEN 'run'
                                                    ELSE 'skip'
                                                END AS execute_sp
                                            FROM job_on_progress
                                            WHERE job_name = 'LISBAN'
                                        ");

                if (!empty($checkRunSp) && $checkRunSp[0]->execute_sp === 'run') {
                    DB::select('CALL lisban_berjalan(?)', [$getNow]);
                }

                $query = $query2;
            } else {
                $query = $query1;
            }

            if ($getBranchIdUser != '8593fd4e-b54e-11ef-97d5-bc24112eb731') {
                $query .= " AND st.arr_count <= 8";
            }

            if (strtolower($getPosition) != 'ho') {
                $query .= " AND cl.BRANCH = '$getBranchIdUser'";
            } else {
                if (!empty($getBranch) && $getBranch != 'SEMUA CABANG') {
                    $query .= " AND cl.BRANCH = '$getBranch'";
                }
            }

            $query .= " ORDER BY b.NAME,cl.CREATED_AT ASC";

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
                    "KECAMATAN" => $result->KODE_POST ?? '',
                    "KELURAHAN" => $result->SUB_ZIP ?? '',
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
                    "AMBC PKK AWAL" => intval($result->AMBC_PKK_AWAL),
                    "AMBC BNG AWAL" => intval($result->AMBC_BNG_AWAL),
                    "AMBC TOTAL AWAL" => intval($result->AMBC_TOTAL_AWAL),
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => intval($result->OS_PKK_AKHIR) ?? 0,
                    "OUTS BNG AKHIR" => intval($result->OS_BNG_AKHIR) ?? 0,
                    "OVERDUE AKHIR" => intval($result->OVERDUE_AKHIR) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' : date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : date("d-m-Y", strtotime($result->LAST_PAY ?? '')),
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
                    "NAMA BRG" => $result->jenis_jaminan,
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" =>  $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => intval($result->NILAI_PINJAMAN) ?? 0,
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

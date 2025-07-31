<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

                    if ($item->JENIS != 'PENCAIRAN' && $item->JENIS != '') {
                        if ($amount != 0) {

                            if ($item->angsuran_ke == 'PEMBULATAN') {
                                $keterangan = $item->angsuran_ke . ' (' . $item->no_invoice . ')';
                            } else {
                                $keterangan = 'BAYAR ' . $item->angsuran_ke . ' (' . $item->no_invoice . ')';
                            }

                            $setType = $item->JENIS == 'PELUNASAN' || $item->JENIS == 'PELUNASAN PINALTY' || $item->JENIS == 'PEMBULATAN PELUNASAN' || $item->JENIS == 'DENDA PELUNASAN' ? 'PELUNASAN' : 'CASH_IN';

                            $datas['datas'][] = [
                                'type' => $setType,
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
        $dari = $request->dari;
        $sampai = $request->sampai;

        $query = DB::table('lkbh_report_view')->whereBetween('ENTRY_DATE', [$dari, $sampai]);

        if (!empty($cabangId) && $cabangId !== 'SEMUA CABANG') {
            $query->where('BRANCH_ID', $cabangId);
        }

        $results = $query->orderByRaw('ENTRY_DATE, position, LOAN_NUM, no_invoice, angsuran_ke')->get();

        return $results;
    }

    public function listBanTest(Request $request)
    {
        try {
            $dateFrom = $request->dari;
            $getBranch = $request->cabang_id;
            $getPosition = $request->user()->position;
            $getUserName = $request->user()->fullname;

            $getBranchIdUser = $request->user()->branch_id;
            $getNow = date('mY', strtotime(now()));

            $checkConditionDate = $getNow == $dateFrom;

            $jobName = $checkConditionDate ? 'LISBAN' : 'LISBAN_BELOM_MOVEON';

            $checkQueue = DB::table('job_on_progress')->where('JOB_NAME', $jobName)->first();

            $lastCallTime = Carbon::parse($checkQueue->LAST_CALL)->setTimezone('Asia/Jakarta');
            $now = Carbon::now('Asia/Jakarta');

            $diffInMinutes = $lastCallTime->diffInMinutes($now);

            // if ($checkQueue->JOB_STATUS == 1) {

            if ($checkQueue->JOB_STATUS == 1 && $diffInMinutes < 5) {
                throw new Exception("RUNNING JOB", 408);
            }

            $query1 = "SELECT  CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.NAME AS NAMA_CABANG,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                c.NAME AS NAMA_PELANGGAN,
                                cl.CREATED_AT AS TGL_BOOKING,
                                NULL AS UB,
                                NULL AS PLATFORM,
                                CONCAT(c.INS_ADDRESS,' RT/', c.INS_RT, ' RW/', c.INS_RW, ' ', c.INS_CITY, ' ', c.INS_PROVINCE) AS ALAMAT_TAGIH,
                                c.INS_KECAMATAN AS KODE_POST,
                                c.INS_KELURAHAN AS SUB_ZIP,
                                c.PHONE_HOUSE AS NO_TELP,
                                c.PHONE_PERSONAL AS NO_HP,
                                c.PHONE_PERSONAL AS NO_HP2,
                                c.OCCUPATION AS PEKERJAAN,
                                CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS supplier,
                                coalesce(u.fullname,cl.mcf_id) AS SURVEYOR,
                                -- cs.survey_note AS CATT_SURVEY,
                                coalesce(cs.survey_note,osn.SURVEY_NOTE) AS CATT_SURVEY,
                                replace(format(cl.PCPL_ORI ,0),',','') AS PKK_HUTANG,
                                cl.PERIOD AS JUMLAH_ANGSURAN,
                                replace(format(cl.PERIOD/cl.INSTALLMENT_COUNT,0),',','') AS JARAK_ANGSURAN,
                                cl.INSTALLMENT_COUNT as PERIOD,
		                        replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.PCPL_ORI
			 			                        else st.init_pcpl end,0),',','') AS OUTSTANDING,
		                        replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.INTRST_ORI
			 			                        else st.init_int end,0),',','') AS OS_BUNGA,
                                case when coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) < 0 then 0
                                    else coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) end as OVERDUE_AWAL,
                                replace(format(coalesce(st.arr_pcpl,0),0),',','') as AMBC_PKK_AWAL,
                                replace(format(coalesce(st.arr_int,0),0),',','') as AMBC_BNG_AWAL,
                                replace(format((coalesce(st.arr_pcpl,0)+coalesce(st.arr_int,0)),0),',','') as AMBC_TOTAL_AWAL,
                                concat('C',case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 'N'
                                                when cl.STATUS_REC = 'RP' and py.ID is null then 'L'
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
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
                                -- case when (cl.PERIOD/cl.INSTALLMENT_COUNT)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar, 
                                replace(format(coalesce(en.init_pcpl,0),0),',','') OS_PKK_AKHIR,
                                replace(format(coalesce(en.init_int,0),0),',','') as OS_BNG_AKHIR,
                                case when coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) < 0 then 0
                                    else coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) end as OVERDUE_AKHIR,
                                cl.INSTALLMENT,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 1
                                			 when coalesce(st.first_arr,en.first_arr) is null then ''
                                			 else coalesce(st.last_inst,en.last_inst) end as LAST_INST,
                                ca.INSTALLMENT_TYPE AS tipe,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end as F_ARR_CR_SCHEDL,
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
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (en.init_pcpl+en.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(en.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),'%m%Y') then 'N'
                                                when en.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 3 month) then 'N'
                                                when en.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when en.arr_count > 8 then 'X'
                                                else en.arr_count end) AS CYCLE_AKHIR,
                                -- case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar_akhir,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar_akhir, 
                                col.COL_TYPE as jenis_jaminan,
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
                                left join temp_lis_03 col on cast(col.LOAN_NUMBER as char) = cast(cl.LOAN_NUMBER as char)
                                left join temp_lis_01 st
                                    on cast(st.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and st.type=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),'%d%m%Y')
                                left join temp_lis_01 en
                                    on cast(en.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and en.type=date_format(date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day),'%d%m%Y')
                                left join temp_lis_02 py on cast(py.loan_num as char) = cast(cl.LOAN_NUMBER as char)
                                left join old_survey_note osn on cast(osn.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                        WHERE	date_format(cl.BACK_DATE,'%d%m%Y')=date_format(date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day),'%d%m%Y')
                                and (cl.STATUS = 'A'
                                        or (cl.STATUS_REC = 'RP' and cl.mod_user <> 'exclude jaminan' and cast(cl.LOAN_NUMBER as char) not in (select cast(pp.LOAN_NUM as char) from payment pp where pp.ACC_KEY = 'JUAL UNIT')) 
                                        or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char)from temp_lis_02 )))";

            $query2 = "SELECT	CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.NAME AS NAMA_CABANG,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                c.NAME AS NAMA_PELANGGAN,
                                cl.CREATED_AT AS TGL_BOOKING,
                                NULL AS UB,
                                NULL AS PLATFORM,
                                CONCAT(c.INS_ADDRESS,' RT/', c.INS_RT, ' RW/', c.INS_RW, ' ', c.INS_CITY, ' ', c.INS_PROVINCE) AS ALAMAT_TAGIH,
                                c.INS_KECAMATAN AS KODE_POST,
                                c.INS_KELURAHAN AS SUB_ZIP,
                                c.PHONE_HOUSE AS NO_TELP,
                                c.PHONE_PERSONAL AS NO_HP,
                                c.PHONE_PERSONAL AS NO_HP2,
                                c.OCCUPATION AS PEKERJAAN,
                                CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS supplier,
                                coalesce(u.fullname,cl.mcf_id) AS SURVEYOR,
                                -- cs.survey_note AS CATT_SURVEY,
                                coalesce(cs.survey_note,osn.SURVEY_NOTE) AS CATT_SURVEY,
                                replace(format(cl.PCPL_ORI ,0),',','') AS PKK_HUTANG,
                                cl.PERIOD AS JUMLAH_ANGSURAN,
                                replace(format(cl.PERIOD/cl.INSTALLMENT_COUNT,0),',','') AS JARAK_ANGSURAN,
                                cl.INSTALLMENT_COUNT as PERIOD,
                                replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.PCPL_ORI
			 			                        else st.init_pcpl end,0),',','') AS OUTSTANDING,
		                        replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.INTRST_ORI
			 			                        else st.init_int end,0),',','') AS OS_BUNGA,
                                case when coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) < 0 then 0
                                    else coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) end as OVERDUE_AWAL,
                                replace(format(coalesce(st.arr_pcpl,0),0),',','') as AMBC_PKK_AWAL,
                                replace(format(coalesce(st.arr_int,0),0),',','') as AMBC_BNG_AWAL,
                                replace(format((coalesce(st.arr_pcpl,0)+coalesce(st.arr_int,0)),0),',','') as AMBC_TOTAL_AWAL,
                                concat('C',case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 'N'
                                                when cl.STATUS_REC = 'RP' and py.ID is null then 'L'
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
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
                                -- case when (cl.PERIOD/cl.INSTALLMENT_COUNT)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar,
                                replace(format(coalesce(en.init_pcpl,0),0),',','') OS_PKK_AKHIR,
                                replace(format(coalesce(en.init_int,0),0),',','') as OS_BNG_AKHIR,
                                case when coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) < 0 then 0
                                    else coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) end as OVERDUE_AKHIR,
                                cl.INSTALLMENT,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 1
                                			 when coalesce(st.first_arr,en.first_arr) is null then ''
                                			 else coalesce(st.last_inst,en.last_inst) end as LAST_INST,
                                ca.INSTALLMENT_TYPE AS tipe,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end as F_ARR_CR_SCHEDL,
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
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (en.init_pcpl+en.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(en.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),'%m%Y') then 'N'
                                                when en.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 3 month) then 'N'
                                                when en.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when en.arr_count > 8 then 'X'
                                                else en.arr_count end) AS CYCLE_AKHIR,
                                -- case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar_akhir,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar_akhir,
                                col.COL_TYPE as jenis_jaminan,
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
                                left join old_survey_note osn on cast(osn.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                        WHERE	(cl.STATUS = 'A'  
                                    or (cl.STATUS_REC = 'RP' and cl.mod_user <> 'exclude jaminan' and cast(cl.LOAN_NUMBER as char) not in (select cast(pp.LOAN_NUM as char) from payment pp where pp.ACC_KEY = 'JUAL UNIT'))
                                    or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char) from temp_lis_02C )))";



            if ($checkConditionDate) {

                $checkRunSp = DB::select("  SELECT
                                            CASE
                                                WHEN (SELECT MAX(p.ENTRY_DATE) FROM payment p) >= (SELECT coalesce(MAX(temp_lis_02C.last_pay),(SELECT MAX(p.ENTRY_DATE) FROM payment p)) FROM temp_lis_02C)
                                                    AND job_status = 0 THEN 'run'
                                                ELSE 'skip'
                                            END AS execute_sp
                                            FROM job_on_progress
                                            WHERE job_name = 'LISBAN'");

                // $checkRunSp = DB::select(" SELECT
                //                                 CASE
                //                                     WHEN (SELECT MAX(p.ENTRY_DATE) FROM payment p) >= (SELECT MAX(temp_lis_02C.last_pay) FROM temp_lis_02C)
                //                                         AND job_status = 0 THEN 'run'
                //                                     ELSE 'skip'
                //                                 END AS execute_sp
                //                             FROM job_on_progress
                //                             WHERE job_name = 'LISBAN'");

                if (!empty($checkRunSp) && $checkRunSp[0]->execute_sp === 'run') {
                    DB::select('CALL lisban_berjalan(?,?)', [$getNow, $getUserName]);
                }

                $query = $query2;
            } else {

                $checkRunSp = DB::select(" SELECT
                                                CASE
                                                    WHEN job_status = 0 THEN 'run'
                                                    ELSE 'skip'
                                                END AS execute_sp
                                            FROM job_on_progress
                                            WHERE job_name = 'LISBAN_BELOM_MOVEON'");

                if (!empty($checkRunSp) && $checkRunSp[0]->execute_sp === 'run') {
                    DB::select('CALL lisban_masa_lalu(?,?)', [$dateFrom, $getUserName]);
                }

                $query = $query1;
            }

            if ($getBranchIdUser != '8593fd4e-b54e-11ef-97d5-bc24112eb731') {
                $query .= " AND coalesce(st.arr_count,0) <= 8";
            }

            if (strtolower($getPosition) != 'ho') {
                $query .= " AND cl.BRANCH = '$getBranchIdUser'";
            } else {
                if (!empty($getBranch) && $getBranch != 'SEMUA CABANG' && $getBranch != '8593fd4e-b54e-11ef-97d5-bc24112eb731') {
                    $query .= " AND cl.BRANCH = '$getBranch'";
                }
            }

            $query .= " ORDER BY b.NAME,cl.CREATED_AT ASC";

            $results = DB::select($query);

            $jobName = $checkConditionDate ? 'LISBAN' : 'LISBAN_BELOM_MOVEON';
            DB::select("UPDATE job_on_progress SET job_status = 0, last_user='' WHERE job_name = ?", [$jobName]);

            $build = [];
            foreach ($results as $result) {

                $getUsers = User::find($result->SURVEYOR);

                $build[] = [
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" => is_numeric($result->NO_KONTRAK) ? intval($result->NO_KONTRAK ?? '') : $result->NO_KONTRAK ?? '',
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ?  Carbon::parse($result->TGL_BOOKING)->format('m/d/Y') : '',
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
                    "JRK ANGS" => intval($result->JARAK_ANGSURAN ?? ''),
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
                    "POLA BYR AWAL" => '',
                    "OUTS PKK AKHIR" => intval($result->OS_PKK_AKHIR) ?? 0,
                    "OUTS BNG AKHIR" => intval($result->OS_BNG_AKHIR) ?? 0,
                    "OVERDUE AKHIR" => intval($result->OVERDUE_AKHIR) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => intval($result->LAST_INST ?? ''),
                    "TIPE ANGSURAN" => $result->pola_bayar === 'bunga_menurun' ? str_replace('_', ' ', $result->pola_bayar) : $result->pola_bayar ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' :  Carbon::parse($result->F_ARR_CR_SCHEDL)->format('m/d/Y'),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : Carbon::parse($result->curr_arr)->format('m/d/Y'),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : Carbon::parse($result->LAST_PAY)->format('d/n/Y'),
                    "KOLEKTOR" => $result->COLLECTOR,
                    "CARA BYR" => $result->cara_bayar,
                    "AMBC PKK_AKHIR" => intval($result->AMBC_PKK_AKHIR) ?? 0,
                    "AMBC BNG_AKHIR" => intval($result->AMBC_BNG_AKHIR) ?? 0,
                    "AMBC TOTAL_AKHIR" => intval($result->AMBC_TOTAL_AKHIR) ?? 0,
                    "AC PKK" => intval($result->AC_PKK),
                    "AC BNG MRG" => intval($result->AC_BNG_MRG),
                    "AC TOTAL" => intval($result->AC_TOTAL),
                    "CYCLE AKHIR" => $result->CYCLE_AKHIR,
                    "POLA BYR AKHIR" => '',
                    "NAMA BRG" => $result->jenis_jaminan,
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" => (int) $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => intval($result->NILAI_PINJAMAN) ?? 0,
                    "ADMIN" =>  intval($result->TOTAL_ADMIN) ?? '',
                    "CUST_ID" => is_numeric($result->CUST_CODE) ? intval($result->CUST_CODE ?? '') : $result->CUST_CODE ?? ''
                ];
            }

            return response()->json($build, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

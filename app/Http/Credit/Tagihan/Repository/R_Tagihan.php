<?php

namespace App\Http\Credit\Tagihan\Repository;

use App\Http\Credit\Tagihan\Model\M_Tagihan;
use Illuminate\Support\Facades\DB;

class R_Tagihan
{
    protected $model;

    public function __construct(M_Tagihan $model)
    {
        $this->model = $model;
    }

    //   CASE
    //                             WHEN (
    //                                 CONCAT('C',
    //                                     CASE 
    //                                         WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 'N'
    //                                         WHEN cl.STATUS_REC = 'RP' AND py.ID IS NULL AND DATE_FORMAT(col.SITA_AT, '%m%Y') <> '$dateFrom' THEN 'L'
    //                                         WHEN REPLACE(FORMAT(
    //                                                 CASE 
    //                                                     WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' 
    //                                                     THEN (cl.PCPL_ORI + cl.INTRST_ORI)
    //                                                     ELSE (st.init_pcpl + st.init_int)
    //                                                 END, 0), ',', '') = '0' THEN 'L'
    //                                         WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) = 1 
    //                                             AND st.first_arr > LAST_DAY(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y')) THEN 'M'
    //                                         WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) <> 1 
    //                                             AND DATE_FORMAT(st.first_arr, '%m%Y') = DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 1 MONTH), '%m%Y') THEN 'N'
    //                                         WHEN st.first_arr >= DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 2 MONTH) THEN 'N'
    //                                         WHEN st.arr_count > 8 THEN 'X'
    //                                         ELSE st.arr_count
    //                                     END
    //                                 ) IN ('CM', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0')
    //                                 AND (
    //                                     CASE 
    //                                         WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 1
    //                                         WHEN COALESCE(st.first_arr, en.first_arr) IS NULL THEN ''
    //                                         ELSE COALESCE(st.last_inst, en.last_inst)
    //                                     END
    //                                 ) IN (1, 2, 3)
    //                                 AND u.keterangan = 'AKTIF'
    //                             )
    //                             THEN 'Y'
    //                             WHEN (
    //                                 CONCAT('C',
    //                                     CASE 
    //                                         WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 'N'
    //                                         WHEN cl.STATUS_REC = 'RP' AND py.ID IS NULL AND DATE_FORMAT(col.SITA_AT, '%m%Y') <> '$dateFrom' THEN 'L'
    //                                         WHEN REPLACE(FORMAT(
    //                                                 CASE 
    //                                                     WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' 
    //                                                     THEN (cl.PCPL_ORI + cl.INTRST_ORI)
    //                                                     ELSE (st.init_pcpl + st.init_int)
    //                                                 END, 0), ',', '') = '0' THEN 'L'
    //                                         WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) = 1 
    //                                             AND st.first_arr > LAST_DAY(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y')) THEN 'M'
    //                                         WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) <> 1 
    //                                             AND DATE_FORMAT(st.first_arr, '%m%Y') = DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 1 MONTH), '%m%Y') THEN 'N'
    //                                         WHEN st.first_arr >= DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 2 MONTH) THEN 'N'
    //                                         WHEN st.arr_count > 8 THEN 'X'
    //                                         ELSE st.arr_count
    //                                     END
    //                                 ) IN ('CM', 'C0', 'C1', 'C2')
    //                                 AND (
    //                                     CASE 
    //                                         WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 1
    //                                         WHEN COALESCE(st.first_arr, en.first_arr) IS NULL THEN ''
    //                                         ELSE COALESCE(st.last_inst, en.last_inst)
    //                                     END
    //                                 ) IN (1, 2, 3)
    //                                 AND u.keterangan = 'RESIGN'
    //                             )
    //                             THEN 'Y' ELSE 'N' END AS nbot


    // case    when (	concat('C',case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 'N'
    //                                     when cl.STATUS_REC = 'RP' and py.ID is null and date_format(col.SITA_AT,'%m%Y')<>'$dateFrom'  then 'L'
    //                                     when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
    //                                                         else (st.init_pcpl+st.init_int) end,0),',','')=0 then 'L'
    //                                     when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
    //                                             and date_format(st.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),'%m%Y') then 'N'
    //                                     when st.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month) then 'N'
    //                                     when st.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
    //                                             and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
    //                                     when st.arr_count > 8 then 'X'
    //                                     else st.arr_count end)) in ('CM','C5','C4','C3','C2','C1','C0') 
    //                                     and (case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 1
    //                             when coalesce(st.first_arr,en.first_arr) is null then ''
    //                             else coalesce(st.last_inst,en.last_inst) end) in (1,2,3) then 'Y' else 'N' 
    //                             end as nbot

    protected function getAllListTagihan($request)
    {
        $dateFrom = date('mY', strtotime(now()));
        $currentBranch = $request->user()->branch_id;
        $currentPosition = $request->user()->position;
        $getUserName = "Get Deploy";

        if ($currentPosition != 'HO') {
            $showCycle = "'CM','C8','C7','C6','C5','C4','C3','C2','C1','C0'";
        } else {
            $showCycle = "'CM','CX','C8','C7','C6','C5','C4','C3','C2','C1','C0'";
        }

        $sql = "SELECT	        CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                cl.ID AS CREDIT_ID,
                                cl.CUST_CODE AS CUST_CODE,
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
                                coalesce(u.keterangan,'RESIGN') AS SURVEYOR_STATUS,
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
                                cl.STATUS_REC as STATUS_BEBAN,
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
                                py.last_pay as LAST_PAY,
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
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar_akhir,
                                col.COL_TYPE as jenis_jaminan,
                                col.COLLATERAL,
                                col.POLICE_NUMBER,
                                col.ENGINE_NUMBER,
                                col.CHASIS_NUMBER,
                                col.PRODUCTION_YEAR,
                                replace(format(cl.PCPL_ORI-cl.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                                replace(format(cl.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                                us.fullname AS username,
                                 CASE
                                WHEN (
                                    CONCAT('C',
                                        CASE 
                                            WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 'N'
                                            WHEN cl.STATUS_REC = 'RP' AND py.ID IS NULL AND DATE_FORMAT(col.SITA_AT, '%m%Y') <> '$dateFrom' THEN 'L'
                                            WHEN REPLACE(FORMAT(
                                                    CASE 
                                                        WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' 
                                                        THEN (cl.PCPL_ORI + cl.INTRST_ORI)
                                                        ELSE (st.init_pcpl + st.init_int)
                                                    END, 0), ',', '') = '0' THEN 'L'
                                            WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) = 1 
                                                AND st.first_arr > LAST_DAY(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y')) THEN 'M'
                                            WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) <> 1 
                                                AND DATE_FORMAT(st.first_arr, '%m%Y') = DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 1 MONTH), '%m%Y') THEN 'N'
                                            WHEN st.first_arr >= DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 2 MONTH) THEN 'N'
                                            WHEN st.arr_count > 8 THEN 'X'
                                            ELSE st.arr_count
                                        END
                                    ) IN ('CM', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0')
                                    AND (
                                        CASE 
                                            WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 1
                                            WHEN COALESCE(st.first_arr, en.first_arr) IS NULL THEN ''
                                            ELSE COALESCE(st.last_inst, en.last_inst)
                                        END
                                    ) IN (1, 2, 3)
                                    AND coalesce(u.keterangan,'RESIGN') = 'AKTIF'
                                )
                                THEN 'Y'
                                WHEN (
                                    CONCAT('C',
                                        CASE 
                                            WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 'N'
                                            WHEN cl.STATUS_REC = 'RP' AND py.ID IS NULL AND DATE_FORMAT(col.SITA_AT, '%m%Y') <> '$dateFrom' THEN 'L'
                                            WHEN REPLACE(FORMAT(
                                                    CASE 
                                                        WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' 
                                                        THEN (cl.PCPL_ORI + cl.INTRST_ORI)
                                                        ELSE (st.init_pcpl + st.init_int)
                                                    END, 0), ',', '') = '0' THEN 'L'
                                            WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) = 1 
                                                AND st.first_arr > LAST_DAY(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y')) THEN 'M'
                                            WHEN (cl.INSTALLMENT_COUNT / cl.PERIOD) <> 1 
                                                AND DATE_FORMAT(st.first_arr, '%m%Y') = DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 1 MONTH), '%m%Y') THEN 'N'
                                            WHEN st.first_arr >= DATE_ADD(STR_TO_DATE(CONCAT('01', '$dateFrom'), '%d%m%Y'), INTERVAL 2 MONTH) THEN 'N'
                                            WHEN st.arr_count > 8 THEN 'X'
                                            ELSE st.arr_count
                                        END
                                    ) IN ('CM', 'C0', 'C1', 'C2')
                                    AND (
                                        CASE 
                                            WHEN DATE_FORMAT(cl.created_at, '%m%Y') = '$dateFrom' THEN 1
                                            WHEN COALESCE(st.first_arr, en.first_arr) IS NULL THEN ''
                                            ELSE COALESCE(st.last_inst, en.last_inst)
                                        END
                                    ) IN (1, 2, 3)
                                    AND coalesce(u.keterangan,'RESIGN') = 'RESIGN'
                                )
                                THEN 'Y' ELSE 'N' END AS nbot
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
                                left join cl_deploy tg 
                                        ON cast(tg.LOAN_NUMBER as char) = cast(cl.LOAN_NUMBER as char)
                                        AND tg.CREATED_AT < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY), INTERVAL 1 MONTH)
                                left join users us on us.username = tg.USER_ID
                        WHERE	(cl.STATUS = 'A'  
                                    or (cl.STATUS_REC = 'RP' and cl.mod_user <> 'exclude jaminan' and cast(cl.LOAN_NUMBER as char) not in (select cast(pp.LOAN_NUM as char) from payment pp where pp.ACC_KEY = 'JUAL UNIT'))
                                    or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char) from temp_lis_02C )))
                                -- AND (tg.LOAN_NUMBER IS NULL OR tg.LOAN_NUMBER = '')
                                AND concat('C',case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 'N'
                                                when cl.STATUS_REC = 'RP' and py.ID is null then 'L'
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (st.init_pcpl+st.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(st.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),'%m%Y') then 'N'
                                                when st.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month) then 'N'
                                                when st.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when st.arr_count > 8 then 'X'
                                                else st.arr_count end) in ($showCycle)";

        if ($currentPosition != 'HO') {
            $sql .= " AND cl.BRANCH = '$currentBranch'";
        }

        // $checkRunSp = DB::select("  SELECT
        //                                     CASE
        //                                         WHEN (SELECT MAX(p.ENTRY_DATE) FROM payment p) >= (SELECT coalesce(MAX(temp_lis_02C.last_pay),(SELECT MAX(p.ENTRY_DATE) FROM payment p)) FROM temp_lis_02C)
        //                                             AND job_status = 0 THEN 'run'
        //                                         ELSE 'skip'
        //                                     END AS execute_sp
        //                                     FROM job_on_progress
        //                                     WHERE job_name = 'LISBAN'");

        // $branchCon = $currentPosition == 'HO' ? '%' : $currentBranch;

        // if (!empty($checkRunSp) && $checkRunSp[0]->execute_sp === 'run') {
        //     DB::select('CALL lisban_berjalan(?,?,?)', [$dateFrom, $getUserName, $branchCon]);
        // }

        $sql .= " ORDER BY COALESCE(u.fullname, cl.mcf_id),cl.LOAN_NUMBER ASC";

        return DB::select($sql);
    }

    protected function listTagihanWithCreditSchedule($loanNumber)
    {
        return  $this->model::with(['credit_schedule'])->where('LOAN_NUMBER', $loanNumber)->get();
    }

    public function listTagihan($branchId = null)
    {
        $query = $this->model::with([
            'customer:CUST_CODE,NAME,ADDRESS,KELURAHAN,KECAMATAN',
            'assignUser:username,fullname'
        ])
            ->select([
                'ID',
                'USER_ID',
                'NO_SURAT',
                'LOAN_NUMBER',
                'CUST_CODE',
                'CYCLE_AWAL',
                'N_BOT',
                'MCF',
                'ANGSURAN_KE',
                'TGL_JTH_TEMPO',
                'ANGSURAN',
                'BRANCH_ID',
                'STATUS',
                'created_at'
            ])
            ->where('STATUS', 'Aktif')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'));

        if ($branchId !== null) {
            $query->where('BRANCH_ID', $branchId);
        }

        return $query->get();
    }


    protected function cl_deploy_by_pic($pic)
    {
        return  $this->model::where('USER_ID', $pic)->get();
    }

    protected function findByLoanNumber($loanNumber)
    {
        return $this->model
            ->where('LOAN_NUMBER', $loanNumber)
            ->whereMonth('CREATED_AT', now()->month)
            ->whereYear('CREATED_AT', now()->year)
            ->first();
    }

    protected function getListTagihanByUserUsername($userId)
    {
        return $this->model::with(['tagihan_detail'])->where('USER_ID', $userId)->get();
    }

    protected function create($fields)
    {
        return $this->model->create($fields);
    }

    protected function update($id, $data)
    {
        $record = $this->model::find($id);

        if ($record) {
            $record->update($data);
            return $record;
        }

        return null;
    }


    protected function deleteByUserId($userId)
    {
        return $this->model->where('USER_ID', $userId)->delete();
    }
}

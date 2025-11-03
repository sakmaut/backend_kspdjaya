<?php

namespace App\Console\Commands;

use App\Models\M_ListbanData;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListanService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:listan-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $jakartaTime = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $dateFrom = $jakartaTime->format('mY');

        $sql = "SELECT	CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.ID AS BRANCH_ID,
                                b.NAME AS NAMA_CABANG,
                                cl.ID AS CREDIT_ID,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                cl.CUST_CODE AS CUST_CODE,
                                cl.CREATED_AT AS TGL_BOOKING,
                                CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS SUPPLIER,
                                cl.mcf_id AS SURVEYOR,
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
                                en.first_arr as CURR_ARR,
                                py.last_pay as LAST_PAY,
                                k.kolektor AS COLLECTOR,
                                py.payment_method as CARA_BAYAR,
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
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as POLA_BAYAR_AKHIR,
                                replace(format(cl.PCPL_ORI-cl.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                                replace(format(cl.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN
                        FROM	credit cl
                                inner join branch b on cast(b.ID as char) = cast(cl.BRANCH as char)
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

        DB::select('CALL lisban_berjalan(?,?,?)', [$dateFrom, "CRON_JOB", "%"]);

        $sql .= " ORDER BY COALESCE(u.fullname, cl.mcf_id),cl.LOAN_NUMBER ASC";

        $results = DB::select($sql);

        if (!empty($results)) {

            M_ListbanData::truncate();

            foreach ($results as $row) {
                M_ListbanData::create([
                    'BRANCH_ID' => $row->BRANCH_ID ?? null,
                    'KODE' => $row->KODE ?? null,
                    'NAMA_CABANG' => $row->NAMA_CABANG ?? null,
                    'CREDIT_ID' => $row->CREDIT_ID ?? null,
                    'NO_KONTRAK' => $row->NO_KONTRAK ?? null,
                    'CUST_CODE' => $row->CUST_CODE ?? null,
                    'SURVEYOR_ID' => $row->SURVEYOR ?? null,
                    'SURVEYOR_STATUS' => $row->SURVEYOR_STATUS ?? null,
                    'CATT_SURVEY' => $row->CATT_SURVEY ?? null,
                    'PKK_HUTANG' => $row->PKK_HUTANG ?? null,
                    'JUMLAH_ANGSURAN' => $row->JUMLAH_ANGSURAN ?? null,
                    'JARAK_ANGSURAN' => $row->JARAK_ANGSURAN ?? null,
                    'PERIOD' => $row->PERIOD ?? null,
                    'OUTSTANDING' => $row->OUTSTANDING ?? null,
                    'OS_BUNGA' => $row->OS_BUNGA ?? null,
                    'OVERDUE_AWAL' => $row->OVERDUE_AWAL ?? null,
                    'AMBC_PKK_AWAL' => $row->AMBC_PKK_AWAL ?? null,
                    'AMBC_BNG_AWAL' => $row->AMBC_BNG_AWAL ?? null,
                    'AMBC_TOTAL_AWAL' => $row->AMBC_TOTAL_AWAL ?? null,
                    'CYCLE_AWAL' => $row->CYCLE_AWAL ?? null,
                    'STATUS_BEBAN' => $row->STATUS_BEBAN ?? null,
                    'POLA_BAYAR' => $row->POLA_BAYAR ?? null,
                    'OS_PKK_AKHIR' => $row->OS_PKK_AKHIR ?? null,
                    'OS_BNG_AKHIR' => $row->OS_BNG_AKHIR ?? null,
                    'OVERDUE_AKHIR' => $row->OVERDUE_AKHIR ?? null,
                    'INSTALLMENT' => $row->INSTALLMENT ?? null,
                    'LAST_INST' => $row->LAST_INST ?? null,
                    'TIPE' => $row->tipe ?? null,
                    'F_ARR_CR_SCHEDL' => $row->F_ARR_CR_SCHEDL ?? null,
                    'CURR_ARR' => $row->CURR_ARR ?? null,
                    'LAST_PAY' => $row->LAST_PAY ?? null,
                    'COLLECTOR' => $row->COLLECTOR ?? null,
                    'CARA_BAYAR' => $row->CARA_BAYAR ?? null,
                    'AMBC_PKK_AKHIR' => $row->AMBC_PKK_AKHIR ?? null,
                    'AMBC_BNG_AKHIR' => $row->AMBC_BNG_AKHIR ?? null,
                    'AMBC_TOTAL_AKHIR' => $row->AMBC_TOTAL_AKHIR ?? null,
                    'AC_PKK' => $row->AC_PKK ?? null,
                    'AC_BNG_MRG' => $row->AC_BNG_MRG ?? null,
                    'AC_TOTAL' => $row->AC_TOTAL ?? null,
                    'CYCLE_AKHIR' => $row->CYCLE_AKHIR ?? null,
                    'POLA_BAYAR_AKHIR' => $row->POLA_BAYAR_AKHIR ?? null,
                    'JENIS_JAMINAN' => null,
                    'NILAI_PINJAMAN' => $row->NILAI_PINJAMAN ?? null,
                    'TOTAL_ADMIN' => $row->TOTAL_ADMIN ?? null,
                    'CREATED_BY' => 'CRON_JOB_SYSTEM',
                    'CREATED_AT' => Carbon::now('Asia/Jakarta'),
                ]);
            }
        }
    }
}

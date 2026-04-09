<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M_VwLoanPaidReports extends Model
{
    use HasFactory;
    protected $table = 'vw_loan_paid_reports';
    protected $fillable = [
        'KODE',
        'NAMA_CABANG',
        'NO_KONTRAK',
        'NAMA_PELANGGAN',
        'TGL_BOOKING',
        'UB',
        'PLATFORM',
        'ALAMAT_TAGIH',
        'KODE_POST',
        'SUB_ZIP',
        'NO_TELP',
        'NO_HP',
        'NO_HP2',
        'PEKERJAAN',
        'supplier',
        'SURVEYOR',
        'CATT_SURVEY',
        'PKK_HUTANG',
        'JUMLAH_ANGSURAN',
        'JARAK_ANGSURAN',
        'PERIOD',
        'OUTSTANDING',
        'OS_BUNGA',
        'OVERDUE_AWAL',
        'AMBC_PKK_AWAL',
        'AMBC_BNG_AWAL',
        'AMBC_TOTAL_AWAL',
        'CYCLE_AWAL',
        'STATUS_REC',
        'STATUS_BEBAN',
        'pola_bayar',
        'OS_PKK_AKHIR',
        'OS_BNG_AKHIR',
        'OVERDUE_AKHIR',
        'INSTALLMENT',
        'LAST_INST',
        'tipe',
        'F_ARR_CR_SCHEDL',
        'curr_arr',
        'LAST_PAY',
        'COLLECTOR',
        'cara_bayar',
        'AMBC_PKK_AKHIR',
        'AMBC_BNG_AKHIR',
        'AMBC_TOTAL_AKHIR',
        'AC_PKK',
        'AC_BNG_MRG',
        'AC_TOTAL',
        'CYCLE_AKHIR',
        'pola_bayar_akhir',
        'jenis_jaminan',
        'COLLATERAL',
        'POLICE_NUMBER',
        'ENGINE_NUMBER',
        'CHASIS_NUMBER',
        'PRODUCTION_YEAR',
        'NILAI_PINJAMAN',
        'TOTAL_ADMIN',
        'CUST_CODE'
    ];

    protected $primaryKey = 'NO_KONTRAK';

    public $incrementing = false;

    public $timestamps = false;
}

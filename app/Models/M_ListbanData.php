<?php

namespace App\Models;

use App\Http\Credit\Tagihan\Model\M_Tagihan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ListbanData extends Model
{
    use HasFactory;
    protected $table = 'listban_data';
    protected $fillable = [
        'ID',
        'BRANCH_ID',
        'KODE',
        'NAMA_CABANG',
        'CREDIT_ID',
        'NO_KONTRAK',
        'CUST_CODE',
        'SURVEYOR_ID',
        'SURVEYOR_STATUS',
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
        'STATUS_BEBAN',
        'POLA_BAYAR',
        'OS_PKK_AKHIR',
        'OS_BNG_AKHIR',
        'OVERDUE_AKHIR',
        'INSTALLMENT',
        'LAST_INST',
        'TIPE',
        'F_ARR_CR_SCHEDL',
        'CURR_ARR',
        'LAST_PAY',
        'COLLECTOR',
        'CARA_BAYAR',
        'AMBC_PKK_AKHIR',
        'AMBC_BNG_AKHIR',
        'AMBC_TOTAL_AKHIR',
        'AC_PKK',
        'AC_BNG_MRG',
        'AC_TOTAL',
        'CYCLE_AKHIR',
        'POLA_BAYAR_AKHIR',
        'JENIS_JAMINAN',
        'NILAI_PINJAMAN',
        'TOTAL_ADMIN',
        'CREATED_BY',
        'CREATED_AT',
    ];

    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if ($model->getKey() == null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }

    public function customer()
    {
        return $this->hasOne(M_Customer::class, 'CUST_CODE', 'CUST_CODE');
    }

    public function deploy()
    {
        return $this->hasOne(M_Tagihan::class, 'LOAN_NUMBER', 'NO_KONTRAK');
    }
}

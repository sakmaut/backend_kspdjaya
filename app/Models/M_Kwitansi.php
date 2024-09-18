<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Kwitansi extends Model
{
    use HasFactory;
    protected $table = 'kwitansi';
    protected $fillable = [
        'ID', 
        'NO_TRANSAKSI', 
        'LOAN_NUMBER', 
        'TGL_TRANSAKSI', 
        'CUST_CODE', 
        'NAMA', 
        'ALAMAT', 
        'RT', 
        'RW', 
        'PROVINSI', 
        'KOTA', 
        'KELURAHAN', 
        'KECAMATAN', 
        'METODE_PEMBAYARAN', 
        'PEMBULATAN', 
        'KEMBALIAN', 
        'JUMLAH_UANG', 
        'TOTAL_BAYAR', 
        'NAMA_BANK', 
        'NO_REKENING', 
        'BUKTI_TRANSFER', 
        'CREATED_BY', 
        'CREATED_AT'
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
}

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
        'PAYMENT_TYPE',
        'PAYMENT_ID',
        'STTS_PAYMENT',
        'NO_TRANSAKSI',
        'LOAN_NUMBER',
        'TGL_TRANSAKSI',
        'CUST_CODE',
        'BRANCH_CODE',
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
        'DISKON',
        'DISKON_FLAG',
        'KEMBALIAN',
        'JUMLAH_UANG',
        'TOTAL_BAYAR',
        'PINALTY_PELUNASAN',
        'DISKON_PINALTY_PELUNASAN',
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

    public function users()
    {
        return $this->belongsTo(User::class, 'CREATED_BY', 'id');
    }

    public function kwitansi_structur_detail()
    {
        return $this->hasMany(M_KwitansiStructurDetail::class, 'no_invoice', 'NO_TRANSAKSI');
    }

    public function kwitansi_pelunasan_detail()
    {
        return $this->hasMany(M_KwitansiDetailPelunasan::class, 'no_invoice', 'NO_TRANSAKSI')->orderBy('angsuran_ke', 'asc');
    }

    public function branch()
    {
        return $this->hasOne(M_Branch::class, 'ID', 'BRANCH_CODE');
    }
}

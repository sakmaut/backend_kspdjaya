<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrOrder extends Model
{
    use HasFactory;
    protected $table = 'cr_order';
    protected $fillable = [
        'ID',
        'APPLICATION_ID',
        'NO_NPWP',
        'BIAYA',
        'ORDER_TANGGAL',
        'ORDER_STATUS',
        'ORDER_TIPE',
        'UNIT_BISNIS',
        'CUST_SERVICE',
        'REF_PELANGGAN',
        'PROG_MARKETING',
        'CARA_BAYAR',
        'KODE_BARANG',
        'ID_TIPE',
        'TAHUN',
        'HARGA_PASAR'
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

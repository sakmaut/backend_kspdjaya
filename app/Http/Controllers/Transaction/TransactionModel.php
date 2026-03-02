<?php

namespace App\Http\Controllers\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionModel extends Model
{
    use HasFactory;
    protected $table = 'transaction_payment';
    protected $fillable = [
        'ID',
        'NO_INVOICE',
        'TIPE',
        'STATUS',
        'METODE',
        'ID_CABANG',
        'LOAN_NUMBER',
        'CUST_CODE',
        'PEMBULATAN',
        'DISKON',
        'FLAG_DISKON',
        'KEMBALIAN',
        'JUMLAH_UANG',
        'TOTAL_BAYAR',
        'BAYAR_BUNGA',
        'BAYAR_PINALTI',
        'BAYAR_POKOK',
        'BAYAR_DENDA',
        'DISKON_POKOK',
        'DISKON_BUNGA',
        'DISKON_PINALTI',
        'DISKON_DENDA',
        'NAMA_BANK',
        'NOMOR_REKENING',
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

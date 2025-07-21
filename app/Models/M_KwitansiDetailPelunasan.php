<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_KwitansiDetailPelunasan extends Model
{
    use HasFactory;
    protected $table = 'kwitansi_detail_pelunasan';
    protected $fillable = [
        'id',
        'no_invoice',
        'loan_number',
        'angsuran_ke',
        'tgl_angsuran',
        'principal',
        'interest',
        'installment',
        'denda',
        'bayar_pokok',
        'bayar_bunga',
        'bayar_denda',
        'diskon_pokok',
        'diskon_bunga',
        'diskon_denda'
    ];
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
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

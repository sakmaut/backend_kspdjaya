<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_KwitansiStructurDetail extends Model
{
    use HasFactory;
    protected $table = 'kwitansi_structur_detail';
    protected $fillable = [
        'id',
        'no_invoice',
        'key',
        'angsuran_ke',
        'loan_number',
        'tgl_angsuran',
        'principal',
        'interest',
        'installment',
        'principal_remains',
        'payment',
        'bayar_angsuran',
        'bayar_denda',
        'total_bayar',
        'flag',
        'denda'
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

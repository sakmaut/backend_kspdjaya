<?php

namespace App\Http\Credit\Tagihan\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Tagihan extends Model
{
    use HasFactory;

    protected $table = 'tagihan';
    protected $fillable = [
        'ID',
        'USER_ID',
        'NO_SURAT',
        'LOAN_NUMBER',
        'TGL_JTH_TEMPO',
        'NAMA_CUST',
        'CYCLE_AWAL',
        'ALAMAT',
        'TGL_EXP',
        'TGL_KUNJUNGAN',
        'KETERANGAN',
        'CREATED_BY',
        'CREATED_AT',
        'UPDATED_BY',
        'UPDATED_AT',
        'DELETED_BY',
        'DELETED_AT'
    ];

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

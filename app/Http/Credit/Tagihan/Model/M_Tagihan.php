<?php

namespace App\Http\Credit\Tagihan\Model;

use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Tagihan extends Model
{
    use HasFactory;

    protected $table = 'cl_deploy';
    protected $fillable = [
        'ID',
        'NO_SURAT',
        'USER_ID',
        'BRANCH_ID',
        'LOAN_NUMBER',
        'TGL_JTH_TEMPO',
        'NAMA_CUST',
        'CYCLE_AWAL',
        'N_BOT',
        'ALAMAT',
        'DESA',
        'KEC',
        'MCF',
        'ANGSURAN_KE',
        'ANGSURAN',
        'BAYAR',
        'TGL_EXP',
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

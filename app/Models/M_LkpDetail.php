<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_LkpDetail extends Model
{
    use HasFactory;
    protected $table = 'cl_lkp_detail';
    protected $fillable = [
        'ID',
        'NO_SURAT',
        'LKP_ID',
        'LOAN_NUMBER',
        'LOAN_HOLDER',
        'ADDRESS',
        'DESA',
        'KEC',
        'DUE_DATE',
        'CYCLE',
        'INST_COUNT',
        'PRINCIPAL',
        'INTEREST',
        'PINALTY',
        'VISIT_TIME',
        'VISIT_RESULT',
        'EVAL_DATE',
        'CREATED_BY',
        'CREATED_AT',
        'UPDATED_BY',
        'UPDATED_AT',
        'DELETED_BY'
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

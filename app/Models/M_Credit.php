<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Credit extends Model
{
    use HasFactory;
    protected $table = 'credit';
    protected $fillable = [
        'ID',
        'LOAN_NUMBER',
        'STATUS_REC',
        'BRANCH',
        'CUST_CODE',
        'ORDER_NUMBER',
        'COLLECTIBILITY',
        'MCF_ID',
        'ENTRY_DATE',
        'END_DATE',
        'FIRST_ARR_DATE',
        'INSTALLMENT_DATE',
        'PCPL_ORI',
        'PAID_PRINCIPAL',
        'PAID_INTEREST',
        'PAID_PENALTY',
        'DUE_PRINCIPAL',
        'DUE_INTEREST',
        'DUE_PENALTY',
        'CREDIT_TYPE',
        'INSTALLMENT_COUNT',
        'PERIOD',
        'INSTALLMENT',
        'FLAT_RATE',
        'EFF_RATE',
        'VERSION',
        'CREATED_BY',
        'CREATED_AT',
        'MOD_DATE',
        'MOD_USER',
        'DELETED_BY',
        'DELETED_AT',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Payment extends Model
{
    use HasFactory;
    protected $table = 'payment';
    protected $fillable = [
        'ID',
        'ACC_KEY',
        'STTS_RCRD',
        'NO_TRX',
        'PAYMENT_METHOD',
        'INVOICE',
        'ACC_NUM',
        'BRANCH',
        'LOAN_NUM',
        'VALUE_DATE',
        'ENTRY_DATE',
        'TITLE',
        'ORIGINAL_AMOUNT',
        'OS_AMOUNT',
        'SUSPENSION_PENALTY_FLAG',
        'CALC_DAYS',
        'SETTLE_ACCOUNT',
        'START_DATE',
        'END_DATE',
        'USER_ID',
        'LAST_JOB_DATE',
        'AUTH_BY',
        'AUTH_DATE',
        'ARREARS_ID',
        'ATTACHMENT',
        'BANK_NAME',
        'BANK_ACC_NUMBER',
        'RECEIPT_TIME'
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

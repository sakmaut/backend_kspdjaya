<?php

namespace App\Http\Controllers\Payment\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CreditScheduleBefore extends Model
{
    use HasFactory;
    protected $table = 'credit_schedule_before';
    protected $fillable = [
        'ID',
        'NO_INVOICE',
        'LOAN_NUMBER',
        'INSTALLMENT_COUNT',
        'PAYMENT_DATE',
        'PRINCIPAL',
        'INTEREST',
        'INSTALLMENT',
        'PRINCIPAL_REMAINS',
        'PAYMENT_VALUE_PRINCIPAL',
        'PAYMENT_VALUE_INTEREST',
        'DISCOUNT_PRINCIPAL',
        'DISCOUNT_INTEREST',
        'INSUFFICIENT_PAYMENT',
        'PAYMENT_VALUE',
        'PAID_FLAG',
        'CREATED_BY',
        'CREATED_AT',
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

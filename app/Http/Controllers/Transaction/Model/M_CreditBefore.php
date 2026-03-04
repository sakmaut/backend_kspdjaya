<?php

namespace App\Http\Controllers\Payment\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CreditBefore extends Model
{
    use HasFactory;
    protected $table = 'credit_before';
    protected $fillable = [
        'ID',
        'NO_INVOICE',
        'LOAN_NUMBER',
        'PCPL_ORI',
        'INTRST_ORI',
        'PAID_PRINCIPAL',
        'PAID_INTEREST',
        'PAID_PENALTY',
        'DISCOUNT_PRINCIPAL',
        'DISCOUNT_INTEREST',
        'DISCOUNT_PENALTY',
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

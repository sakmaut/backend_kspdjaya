<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_PaymentCancelLog extends Model
{
    use HasFactory;
    protected $table = 'payment_cancel_log';
    protected $fillable = [ 
        'ID',
        'INVOICE_NUMBER',
        'REQUEST_BY',
        'REQUEST_BRANCH',
        'REQUEST_POSITION',
        'REQUEST_DESCR',
        'REQUEST_DATE',
        'ONCHARGE_PERSON',
        'ONCHARGE_TIME',
        'ONCHARGE_DESCR',
        'ONCHARGE_FLAG',
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

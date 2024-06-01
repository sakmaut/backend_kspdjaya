<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrApplication extends Model
{
    use HasFactory;
    protected $table = 'cr_application';
    protected $fillable = [
        'ID',
        'CR_PROSPECT_ID',
        'BRANCH',
        'FORM_NUMBER',
        'ORDER_NUMBER',
        'CUST_CODE',
        'ENTRY_DATE',
        'SUBMISSION_VALUE',
        'CREDIT_TYPE',
        'INSTALLMENT_COUNT',
        'PERIOD',
        'INSTALLMENT',
        'FLAT_RATE',
        'EFF_RATE',
        'VERSION',
        'CREATE_DATE',
        'CREATE_BY',
        'MOD_DATE',
        'MOD_BY',
        'DELETED_AT',
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

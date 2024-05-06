<?php

namespace App\Models\CrApplication;

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
       'CLEAR_FLAG',
       'APPLICATION_NUMBER',
       'CUST_CODE',
       'ACCOUNT_NUMBER',
       'SUBMISSION_FLAG',
       'SUBMISSION_VALUE',
       'PERIOD',
       'CREDIT_TYPE',
       'INTENDED_FOR',
       'TERM_OF_PAYMENT',
       'INSTALLMENT_TYPE',
       'VERSION',
       'CREATE_DATE',
       'CREATE_USER',
       'MOD_DATE',
       'MOD_USER'
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

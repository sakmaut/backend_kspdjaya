<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrSurvey extends Model
{
    use HasFactory;
    protected $table = 'cr_survey';
    protected $fillable = [
        'ID', 
        'CR_PROSPECT_ID', 
        'MOTHER_NAME',
        'CATEGORY', 
        'TIN_NUMBER',
        'TITLE', 
        'WORK_PERIOD', 
        'DEPENDANTS', 
        'INCOME_PERSONAL', 
        'INCOME_SPOUSE', 
        'INCOME_OTHER', 
        'EXPENSES', 
        'CUST_CODE_REF',
        'SURVEYOR_ID', 
        'SURVEY_NOTE', 
        'PAYMENT_PREFERENCE', 
        'created_by',
        'created_at', 
        'updated_by', 
        'updated_at', 
        'deleted_by', 
        'deleted_at'
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

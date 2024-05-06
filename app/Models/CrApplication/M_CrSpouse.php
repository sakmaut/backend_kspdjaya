<?php

namespace App\Models\CrApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrSpouse extends Model
{
    use HasFactory;
    protected $table = 'cr_spouse';
    protected $fillable = [
       'ID',
       'CR_APPLICATION_ID',
       'NAME',
       'GENDER',
       'BIRTHPLACE',
       'BIRTHDATE',
       'EDUCATION',
       'ID_NUMBER',
       'ID_ISSUE_DATE',
       'ID_VALID_DATE',
       'OCCUPATION',
       'COMPANY_NAME',
       'COMPANY_SECTION',
       'BUSINESS_PERIOD',
       'POSITION',
       'OFFICE_NUMBER_1',
       'OFFICE_NUMBER_2',
       'MONTHLY_NET_INCOME',
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

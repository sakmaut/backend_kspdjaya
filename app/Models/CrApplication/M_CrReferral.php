<?php

namespace App\Models\CrApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrReferral extends Model
{
    use HasFactory;
    protected $table = 'cr_referral';
    protected $fillable = [
       'ID',
       'CR_APPLICATION_ID',
       'NAME',
       'ADDRESS',
       'CITY',
       'POSTAL_CODE',
       'STAY_PERIOD',
       'PHONE',
       'PERSONAL_NUMBER',
       'RELATIONSHIP',
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

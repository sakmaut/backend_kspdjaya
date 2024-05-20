<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrPersonalExtra extends Model
{
    use HasFactory;
    protected $table = 'cr_personal_extra';
    protected $fillable = [
        'ID',
        'APPLICATION_ID',
        'BI_NAME',
        'EMAIL',
        'INFO',
        'OTHER_OCCUPATION_1',
        'OTHER_OCCUPATION_2',
        'OTHER_OCCUPATION_3',
        'OTHER_OCCUPATION_4',
        'MAIL_ADDRESS',
        'MAIL_RT',
        'MAIL_RW',
        'MAIL_PROVINCE',
        'MAIL_CITY',
        'MAIL_KELURAHAN',
        'MAIL_KECAMATAN',
        'MAIL_ZIP_CODE',
        'EMERGENCY_NAME',
        'EMERGENCY_ADDRESS',
        'EMERGENCY_RT',
        'EMERGENCY_RW',
        'EMERGENCY_PROVINCE',
        'EMERGENCY_CITY',
        'EMERGENCY_KELURAHAN',
        'EMERGENCY_KECAMATAN',
        'EMERGENCY_ZIP_CODE',
        'EMERGENCY_PHONE_HOUSE',
        'EMERGENCY_PHONE_PERSONAL'    
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

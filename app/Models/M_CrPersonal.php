<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrPersonal extends Model
{
    use HasFactory;
    protected $table = 'cr_personal';
    protected $fillable = [
        'ID',
        'APPLICATION_ID',
        'NAME',
        'ALIAS',
        'GENDER',
        'BIRTHPLACE',
        'BIRTHDATE',
        'BLOOD_TYPE',
        'MARTIAL_STATUS',
        'MARTIAL_DATE',
        'ID_TYPE',
        'ID_NUMBER',
        'ID_ISSUE_DATE',
        'ID_VALID_DATE',
        'ADDRESS',
        'RT',
        'RW',
        'PROVINCE',
        'CITY',
        'KELURAHAN',
        'KECAMATAN',
        'ZIP_CODE',
        'KK',
        'CITIZEN',
        'INS_ADDRESS',
        'INS_RT',
        'INS_RW',
        'INS_PROVINCE',
        'INS_CITY',
        'INS_KELURAHAN',
        'INS_KECAMATAN',
        'INS_ZIP_CODE',
        'OCCUPATION',
        'OCCUPATION_ON_ID',
        'RELIGION',
        'EDUCATION',
        'PROPERTY_STATUS',
        'PHONE_HOUSE',
        'PHONE_PERSONAL',
        'PHONE_OFFICE',
        'EXT_1',
        'EXT_2',
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

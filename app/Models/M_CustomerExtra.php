<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CustomerExtra extends Model
{
    use HasFactory;
    protected $table = 'customer_extra';
    protected $fillable = [
        'ID',
        'CUST_CODE',
        'OTHER_OCCUPATION_1',
        'OTHER_OCCUPATION_2',
        'SPOUSE_NAME',
        'SPOUSE_BIRTHPLACE',
        'SPOUSE_BIRTHDATE',
        'SPOUSE_ID_NUMBER',
        'SPOUSE_INCOME',
        'SPOUSE_ADDRESS',
        'SPOUSE_RT',
        'SPOUSE_RW',
        'SPOUSE_PROVINCE',
        'SPOUSE_CITY',
        'SPOUSE_KELURAHAN',
        'SPOUSE_KECAMATAN',
        'SPOUSE_ZIP_CODE',
        'INS_ADDRESS',
        'INS_RT',
        'INS_RW',
        'INS_PROVINCE',
        'INS_CITY',
        'INS_KELURAHAN',
        'INS_KECAMATAN',
        'INS_ZIP_CODE',
        'EMERGENCY_NAME',
        'EMERGENCY_ADDRESS',
        'EMERGENCY_RT',
        'EMERGENCY_RW',
        'EMERGENCY_PROVINCE',
        'EMERGENCYL_CITY',
        'EMERGENCY_KELURAHAN',
        'EMERGENCYL_KECAMATAN',
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

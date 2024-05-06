<?php

namespace App\Models\CrApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrGuarantor extends Model
{
    use HasFactory;
    protected $table = 'cr_guarantor';
    protected $fillable = [
        'ID',
        'CR_APPLICATION_ID',
        'HEADER_ID',
        'NAME',
        'BIRTHPLACE',
        'BIRTHDATE',
        'ID_NUMBER',
        'ADDRESS',
        'CITY',
        'POSTAL_CODE',
        'STAY_PERIOD',
        'PHONE',
        'PERSONAL_NUMBER',
        'RELATION',
        'OCCUPATION',
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

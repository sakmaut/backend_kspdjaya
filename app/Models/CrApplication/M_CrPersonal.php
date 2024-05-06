<?php

namespace App\Models\CrApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrPersonal extends Model
{
    use HasFactory;
    protected $table = 'cr_personal';
    protected $fillable = [
        'ID',
        'CR_APPLICATION_ID',
        'PERSONAL_STATUS',
        'BPR_RELATED_FLAG',
        'NAME',
        'GENDER',
        'BIRTHPLACE',
        'BIRTHDATE',
        'EDUCATION',
        'ID_NUMBER',
        'ID_ISSUE_DATE',
        'ID_VALID_DATE',
        'RELATIONSHIP',
        'RELIGION',
        'AMENABILITY',
        'ADDRESS',
        'PROPERTY_STATUS',
        'CITY',
        'POSTAL_CODE',
        'STAY_PERIOD',
        'PHONE',
        'PERSONAL_NUMBER',
        'MOTHER',
        'TIN_NUMBER',
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

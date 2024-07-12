<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrApplicationGuarantor extends Model
{
    use HasFactory;
    protected $table = 'cr_application_guarantor';
    protected $fillable = [
        'ID',
        'APPLICATION_ID',
        'NAME',
        'GENDER',
        'BIRTHPLACE',
        'BIRTHDATE',
        'ADDRESS',
        'IDENTITY_TYPE',
        'NUMBER_IDENTITY',
        'OCCUPATION',
        'WORK_PERIOD',
        'STATUS_WITH_DEBITUR',
        'MOBILE_NUMBER',
        'INCOME'
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

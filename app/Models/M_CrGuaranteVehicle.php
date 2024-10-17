<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrGuaranteVehicle extends Model
{
    use HasFactory;
    protected $table = 'cr_guarante_vehicle';

    protected $fillable = [
       'ID',
       'CR_SURVEY_ID',
       'HEADER_ID',
       'BRAND',
       'TYPE',
       'PRODUCTION_YEAR',
       'COLOR',
       'ON_BEHALF',
       'POLICE_NUMBER',
       'CHASIS_NUMBER',
       'ENGINE_NUMBER',
       'BPKB_NUMBER',
       'STNK_NUMBER',
       'STNK_VALID_DATE',
       'VALUE',
       'COLLATERAL_FLAG',
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

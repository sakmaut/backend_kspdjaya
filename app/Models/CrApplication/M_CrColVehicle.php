<?php

namespace App\Models\CrApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrColVehicle extends Model
{
    use HasFactory;
    protected $table = 'cr_col_vchl';
    protected $fillable = [
        'ID',
        'CR_APPLICATION_ID',
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
        'VALUE',
        'COLLATERAL_FLAG',
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

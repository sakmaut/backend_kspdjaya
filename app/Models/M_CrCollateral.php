<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrCollateral extends Model
{
    use HasFactory;
    protected $table = 'cr_collateral';

    protected $fillable = [
        'ID',
        'CR_CREDIT_ID',
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
        'BPKB_ADDRESS',
        'STNK_NUMBER',
        'INVOICE_NUMBER',
        'STNK_VALID_DATE',
        'VALUE',
        'COLLATERAL_FLAG',
        'STATUS',
        'VERSION',
        'LOCATION_BRANCH',
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

    public function credit()
    {
        return $this->belongsTo(Credit::class, 'CR_CREDIT_ID', 'ID');
    }
}

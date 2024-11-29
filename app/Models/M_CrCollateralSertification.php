<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrCollateralSertification extends Model
{
    use HasFactory;
    protected $table = 'cr_collateral_sertification';

    protected $fillable = [
        'ID',
        'CR_CREDIT_ID',
        'STATUS_JAMINAN',
        'NO_SERTIFIKAT',
        'STATUS_KEPEMILIKAN',
        'IMB',
        'LUAS_TANAH',
        'LUAS_BANGUNAN',
        'LOKASI',
        'PROVINSI',
        'KAB_KOTA',
        'KECAMATAN',
        'DESA',
        'ATAS_NAMA',
        'NILAI',
        'LOCATION',
        'COLLATERAL_FLAG',
        'STATUS',
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

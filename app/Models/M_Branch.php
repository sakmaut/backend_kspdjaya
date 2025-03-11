<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Branch extends Model
{
    use HasFactory;
    protected $table = 'branch';
    protected $fillable = [
        'ID',
        'CODE',
        'CODE_NUMBER',
        'NAME',
        'ADDRESS',
        'RT',
        'RW',
        'PROVINCE',
        'CITY',
        'KELURAHAN',
        'KECAMATAN',
        'ZIP_CODE',
        'LOCATION',
        'PHONE_1',
        'PHONE_2',
        'PHONE_3',
        'DESCR',
        'STATUS',
        'CREATE_DATE',
        'CREATE_USER',
        'MOD_DATE',
        'MOD_USER',
        'VERSION',
        'DELETED_BY',
        'DELETED_AT'
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

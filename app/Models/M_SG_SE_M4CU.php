<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SG_SE_M4CU extends Model
{
    use HasFactory;
    protected $table = 'sg_se_m4cu';
    protected $fillable = [
       'ID',
       'CUSTAT',
       'CUCODE',
       'CUNAME',
       'CUTITL',
       'CUADR1',
       'CUADR2',
       'CUADR3',
       'CUTELP',
       'CUTEL2',
       'CUFAXN',
       'CUINCO',
       'CUSHOR',
       'CUBRCO',
       'CUFPAR',
       'CUCPAR',
       'CUAOCO',
       'CUIDCO',
       'CUARCO',
       'CUNPWP',
       'CUDTBG',
       'CUAUTU',
       'CUUSER',
       'CUDTLC',
       'CUAGAM',
       'CUKRJA',
       'CUJEKL',
       'CUDTLH',
       'CUNKTP',
       'CUDTID',
       'CUNECO',
       'CUPERS',
       'CUFLTY',
       'CUFLPP',
       'CUOWCO'
    ];
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
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

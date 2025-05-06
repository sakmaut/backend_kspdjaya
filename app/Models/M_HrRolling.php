<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_HrRolling extends Model
{
    use HasFactory;
    protected $table = 'hr_rolling';
    protected $fillable = [
        'ID',
        'NIK',
        'TANGGAL_MULAI',
        'BAGIAN',
        'JABATAN',
        'KANTOR',
        'STATUS',
        'GRADE',
        'TGL_SPK',
        'NO_SPK',
        'SPV',
        'USE_FLAG'
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Taksasi extends Model
{
    use HasFactory;
    protected $table = 'taksasi';

    protected $fillable = [
        'id',
        'brand',
        'code',
        'model',
        'descr',
        'create_at',
        'create_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by'
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

    public function taksasi_price()
    {
        return $this->hasMany(M_TaksasiPrice::class,'taksasi_id');
    }
}

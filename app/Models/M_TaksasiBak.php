<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TaksasiBak extends Model
{
    use HasFactory;
    protected $table = 'taksasi_bak';

    protected $fillable = [
        'id',
        'count',
        'brand',
        'code',
        'model',
        'descr',
        'year',
        'price',
        'created_at',
        'created_by'
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

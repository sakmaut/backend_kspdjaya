<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_OrderResources extends Model
{
    use HasFactory;
    protected $table = 'order_resources';
    protected $fillable = [
        'ID',
        'KODE',
        'NAMA',
        'NO_HP',
        'KETERANGAN',
        'CREATED_BY',
        'CREATED_AT',
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

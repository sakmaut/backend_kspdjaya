<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_BpkbTransaction extends Model
{
    use HasFactory;
    protected $table = 'bpkb_transaction';
    protected $fillable = [
        'ID',
        'TRX_CODE',
        'FROM_BRANCH',
        'TO_BRANCH',
        'CATEGORY',
        'NOTE',
        'STATUS',
        'COURIER',
        'CREATED_BY',
        'CREATED_AT'
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

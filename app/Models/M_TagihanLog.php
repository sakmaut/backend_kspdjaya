<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TagihanLog extends Model
{
    use HasFactory;
    protected $table = 'tagihan_log';
    protected $fillable = [
        'ID',
        'LOAN_NUMBER',
        'LKP_ID',
        'DESCRIPTION',
        'STATUS',
        'CREATED_BY',
        'CREATED_AT',
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

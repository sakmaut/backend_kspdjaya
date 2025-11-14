<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CreditTransaction extends Model
{
    use HasFactory;
    protected $table = 'credit_transaction';
    protected $fillable = [
        'ID',
        'LOAN_NUMBER',
        'ACC_KEYS',
        'AMOUNT',
        'CREATED_BY',
        'CREATED_AT'
    ];


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

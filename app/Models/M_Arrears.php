<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Arrears extends Model
{
    use HasFactory;
    protected $table = 'arrears';
    protected $fillable = [
        'ID',
        'STATUS_REC',
        'LOAN_NUMBER',
        'START_DATE',
        'END_DATE',
        'PAST_DUE_PCPL',
        'PAST_DUE_INTRST',
        'PAST_DUE_PENALTY',
        'PAID_PCPL',
        'PAID_INT',
        'PAID_PENALTY',
        'WOFF_PCPL',
        'WOFF_INT',
        'WOFF_PENALTY',
        'PENALTY_RATE',
        'TRNS_CODE'
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

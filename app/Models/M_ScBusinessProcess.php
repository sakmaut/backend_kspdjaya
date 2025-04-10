<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ScBusinessProcess extends Model
{
    use HasFactory;
    protected $table = 'sc_business_process';
    protected $fillable = [
        'ID',
        'SC_SCORING_ID',
        'product_type',
        'marketing_area',
        'buyer_dependency',
        'competition_level',
        'market_strategy',
        'description'
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

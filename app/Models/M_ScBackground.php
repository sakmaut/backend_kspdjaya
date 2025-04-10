<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ScBackground extends Model
{
    use HasFactory;
    protected $table = 'sc_background';
    protected $fillable = [
        'ID',
        'SC_SCORING_ID',
        'attitude_during_interview',
        'data_providing_ease',
        'slik_reputation',
        'residence_status',
        'key_business_actors',
        'residential_environment',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ScBusiness extends Model
{
    use HasFactory;
    protected $table = 'sc_business';
    protected $fillable = [
        'ID',
        'SC_SCORING_ID',
        'business_location',
        'supplier_sources',
        'business_location_condition',
        'facilities_infrastructure',
        'number_of_employees',
        'supplier_dependency',
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

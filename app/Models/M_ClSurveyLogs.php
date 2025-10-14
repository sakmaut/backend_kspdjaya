<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ClSurveyLogs extends Model
{
    use HasFactory;
    protected $table = 'cl_survey_logs';
    protected $fillable = [
        'ID',
        'REFERENCE_ID',
        'DESCRIPTION',
        'CONFIRM_DATE',
        'PATH',
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

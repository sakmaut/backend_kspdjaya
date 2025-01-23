<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_LogPrint extends Model
{
    use HasFactory;
    protected $table = 'log_print';
    protected $fillable = [
        'ID',
        'COUNT',
        'PRINT_BRANCH',
        'PRINT_POSITION',
        'PRINT_BY',
        'PRINT_DATE'
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

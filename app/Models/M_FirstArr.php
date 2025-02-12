<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_FirstArr extends Model
{
    use HasFactory;
    protected $table = 'first_arr';
    protected $fillable = [
        'LOAN_NUMBER',
        'FIRST_DATE',
        'DUE_DAYS'
    ];
    protected $guarded = [];
    protected $primaryKey = 'LOAN_NUMBER';
    public $incrementing = false;
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

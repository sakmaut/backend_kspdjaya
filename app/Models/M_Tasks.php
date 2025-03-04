<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Tasks extends Model
{
    use HasFactory;
    protected $table = 'tasks';

    protected $fillable = [
        'ID',
        'BRANCH',
        'CHANNEL',
        'IDENTIFIER',
        'MESSAGE',
        'CREATED_AT',
        'STATUS',
        'READ_AT',
        'USER_ACCESS',
        'CREATED_BY'
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

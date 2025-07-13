<?php

namespace App\Http\Controllers\Saving\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SavingDocument extends Model
{
    protected $table = 'saving_document';
    protected $fillable = [
        'ID',
        'TYPE',
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

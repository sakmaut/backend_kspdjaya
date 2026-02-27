<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrBlacklist extends Model
{
    use HasFactory;
    protected $table = 'cr_blacklist';
    protected $fillable = [
       'ID',
       'LOAN_NUMBER',
       'KTP',
       'KK',
       'COLLATERAL',
       'RES_1',
       'RES_2',
       'STATUS',
       'PERSON',
       'DATE_ADD',
       'UPDATED_BY',
       'UPDATED_AT',
       'NOTE'
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

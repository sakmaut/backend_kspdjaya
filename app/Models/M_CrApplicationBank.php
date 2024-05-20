<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrApplicationBank extends Model
{
    use HasFactory;
    protected $table = 'cr_application_bank';
    protected $fillable = [
       'ID',
       'APPLICATION_ID',
       'BANK_CODE',
       'BANK_NAME',
       'ACCOUNT_NUMBER',
       'ACCOUNT_NAME',
       'PREFERENCE_FLAG',
       'STATUS'    
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

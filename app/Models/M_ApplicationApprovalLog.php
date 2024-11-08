<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ApplicationApprovalLog extends Model
{
    use HasFactory;
    protected $table = 'application_approval_log';
    protected $fillable = [
        'ID',
        'CODE',
        'POSITION',
        'APPLICATION_APPROVAL_ID',
        'ONCHARGE_APPRVL',
        'ONCHARGE_PERSON',
        'ONCHARGE_TIME',
        'ONCHARGE_DESCR',
        'APPROVAL_RESULT'
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

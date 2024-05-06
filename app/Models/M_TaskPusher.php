<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TaskPusher extends Model
{
    use HasFactory;
    protected $table = 'task_pusher';

    protected $fillable = [
       'ID',
       'TASK_ID',
       'USER_ID',
       'CREATED',
       'LAST_UPDATE',
       'STATUS',
       'TYPE'
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

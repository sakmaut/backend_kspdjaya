<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TasksLogging extends Model
{
    use HasFactory;
    protected $table = 'tasks_logging';

    protected $fillable = [
        'id',
        'type',
        'type_id',
        'code',
        'status',
        'descr',
        'created_by',
        'created_branch',
        'created_position',
        'created_at'
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

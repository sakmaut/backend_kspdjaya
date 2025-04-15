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
        'id',
        'type',
        'type_id',
        'status',
        'descr',
        'recipient_id',
        'created_id',
        'created_by',
        'created_branch',
        'created_position',
        'created_at'
    ];

    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
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

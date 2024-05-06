<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Role extends Model
{
    use HasFactory;
    protected $table = 'master_role';

    protected $fillable = [
        'id',
        'role_name',
        'status',
        'created_by',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at'
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

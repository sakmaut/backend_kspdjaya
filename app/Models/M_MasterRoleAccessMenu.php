<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_MasterRoleAccessMenu extends Model
{
    use HasFactory;
    protected $table = 'master_role_access_menu';
    protected $fillable = [
        'id',
        'master_menu_id',
        'master_role_id',
        'view',
        'insert',
        'update',
        'delete',
        'import',
        'export',
        'created_by',
        'updated_at',
        'updated_by',
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

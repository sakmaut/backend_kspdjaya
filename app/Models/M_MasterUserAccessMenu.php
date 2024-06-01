<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_MasterUserAccessMenu extends Model
{
    use HasFactory;
    protected $table = 'master_users_access_menu';
    protected $fillable = [
        'id',
        'master_menu_id',
        'users_id',
        'created_at',
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

    public function masterMenu()
    {
        return $this->belongsTo(M_MasterMenu::class, 'master_menu_id');
    }
}

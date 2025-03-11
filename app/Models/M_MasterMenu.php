<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class M_MasterMenu extends Model
{
    use HasFactory;
    protected $table = 'master_menu';
    protected $fillable = [
        'id',
        'menu_name',
        'route',
        'parent',
        'order',
        'leading',
        'action',
        'status',
        'ability',
        'created_by',
        'created_at',
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

    public function accessMenus()
    {
        return $this->hasMany(M_MasterUserAccessMenu::class, 'master_menu_id', 'id');
    }

    static public function getParentMenuName($parentId, $arr = true)
    {
        $parentMenu = self::find($parentId);

        if ($arr) {
            return $parentMenu ? $parentMenu : null;
        } else {
            return $parentMenu ? $parentMenu->menu_name : null;
        }
    }
}

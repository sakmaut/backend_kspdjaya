<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

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

    static public function getParentMenuName($parentId, $arr = true)
    {
        $parentMenu = self::find($parentId);

        if ($arr) {
            return $parentMenu ? $parentMenu : null;
        } else {
            return $parentMenu ? $parentMenu->menu_name : null;
        }
    }


    static function buildMenuArray($menuItems)
    {
        $menuArray = [];
        foreach ($menuItems as $menuItem) {
            if ($menuItem['parent'] === '0' && $menuItem['status'] === 'active') {
                $menuArray[$menuItem['id']] = [
                    'menuid' => $menuItem['id'],
                    'menuitem' => [
                        'labelmenu' => $menuItem['menu_name'],
                        'routename' => $menuItem['route'],
                        'leading' => explode(',', $menuItem['leading']),
                        'action' => $menuItem['action'],
                        'ability' => $menuItem['ability'],
                        'submenu' => []
                    ]
                ];
            }
        }

        foreach ($menuItems as $menuItem) {
            if (!isset($menuArray[$menuItem['parent']])) {
                $menuArray[$menuItem['id']] = [
                    'menuid' => $menuItem['id'],
                    'menuitem' => [
                        'labelmenu' => $menuItem['menu_name'],
                        'routename' => $menuItem['route'],
                        'leading' => explode(',', $menuItem['leading']),
                        'action' => $menuItem['action'],
                        'ability' => $menuItem['ability'],
                        'submenu' => []
                    ]
                ];
            }
        }

        foreach ($menuItems as $menuItem) {
            if ($menuItem['parent'] !== '0' && isset($menuArray[$menuItem['parent']])) {
                $menuArray[$menuItem['parent']]['menuitem']['submenu'][] = [
                    'subid' => $menuItem['id'],
                    'sublabel' => $menuItem['menu_name'],
                    'subroute' => $menuItem['route'],
                    'leading' => explode(',', $menuItem['leading']),
                    'action' => $menuItem['action'],
                    'ability' => $menuItem['ability']
                ];
            }
        }

        return array_values($menuArray);
    }

    public function getSubMenu(Request $request)
    {
        $menu_id = $request->menu_id;

        $query = M_MasterMenu::where('id', $menu_id)->first();

        $data = [
            'menuid' => $query->id,
            'query' => [
                'labelmenu' => $query->menu_name,
                'routename' => $query->route,
                'leading' => explode(',', $query->leading),
                'action' => $query->action,
                'ability' => $query->ability,
                'submenu' => []
            ]
        ];

        return response()->json(['message' => 'OK', "status" => 200, 'response' => self::buildSubMenu($data)], 200);
    }

    function buildSubMenu($menuItems)
    {
        $menu_id =  $menuItems['menuid'];

        $querySubMenu = M_MasterMenu::where('parent', $menu_id)->get();

        foreach ($querySubMenu as $menuItem) {
            $menuItems['submenu'][] = [
                'subid' => $menuItem['id'],
                'sublabel' => $menuItem['menu_name'],
                'subroute' => $menuItem['route'],
                'leading' => explode(',', $menuItem['leading']),
                'action' => $menuItem['action'],
                'ability' => $menuItem['ability']
            ];
        }

        return $menuItems;
    }
}

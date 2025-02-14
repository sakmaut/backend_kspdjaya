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

     static function queryMenu($req){
        $menuItems = self::query()
                        ->select('master_menu.*')
                        ->join('master_users_access_menu as t1', 'master_menu.id', '=', 't1.master_menu_id')
                        ->where('t1.users_id', $req->user()->id)
                        ->where('master_menu.deleted_by', null)
                        ->whereIn('master_menu.status', ['active', 'Active'])
                        ->get();
        
        return $menuItems;  
    }
    static function buildMenuArray($menuItems)
    {
        $listMenu = self::queryMenu($menuItems);
        $menuArray = [];
        $homeParent = null;

        // Find the 'home' parent menu item
        foreach ($listMenu as $menuItem) {
            if ($menuItem->menu_name === 'home' && $menuItem->parent === null) {
                $homeParent = $menuItem;
                break;
            }
        }

        // Initialize the 'home' parent menu in the array
        if ($homeParent) {
            $menuArray[$homeParent->id] = [
                'menuid' => $homeParent->id,
                'menuitem' => [
                    'labelmenu' => $homeParent->menu_name,
                    'routename' => $homeParent->route,
                    'leading' => explode(',', $homeParent->leading),
                    'action' => $homeParent->action,
                    'ability' => $homeParent->ability,
                    'submenu' => []
                ]
            ];
        }

        // Process each menu item to build the menu hierarchy
        foreach ($listMenu as $menuItem) {
            if ($menuItem->parent === null || $menuItem->parent === 0) {
                // If the item has no parent, add it as a root item
                if (!isset($menuArray[$menuItem->id])) {
                    $menuArray[$menuItem->id] = [
                        'menuid' => $menuItem->id,
                        'menuitem' => [
                            'labelmenu' => $menuItem->menu_name,
                            'routename' => $menuItem->route,
                            'leading' => explode(',', $menuItem->leading),
                            'action' => $menuItem->action,
                            'ability' => $menuItem->ability,
                            'submenu' => self::buildSubMenu($menuItem->id, $listMenu)
                        ]
                    ];
                }
            } else {
                // Initialize the parent item if not set
                if (!isset($menuArray[$menuItem->parent])) {
                    $parentMenuItem = M_MasterMenu::find($menuItem->parent);
                    if ($parentMenuItem) {
                        $menuArray[$menuItem->parent] = [
                            'menuid' => $parentMenuItem->id,
                            'menuitem' => [
                                'labelmenu' => $parentMenuItem->menu_name,
                                'routename' => $parentMenuItem->route,
                                'leading' => explode(',', $parentMenuItem->leading),
                                'action' => $parentMenuItem->action,
                                'ability' => $parentMenuItem->ability,
                                'submenu' => []
                            ]
                        ];
                    }
                }

                // Add the current item as a submenu of its parent
                if (!self::menuItemExists($menuArray[$menuItem->parent]['menuitem']['submenu'], $menuItem->id)) {
                    $menuArray[$menuItem->parent]['menuitem']['submenu'][] = [
                        'subid' => $menuItem->id,
                        'sublabel' => $menuItem->menu_name,
                        'subroute' => $menuItem->route,
                        'leading' => explode(',', $menuItem->leading),
                        'action' => $menuItem->action,
                        'ability' => $menuItem->ability,
                        'submenu' => self::buildSubMenu($menuItem->id, $listMenu)
                    ];
                }
            }
        }

        // Re-index submenu arrays for each menu item
        foreach ($menuArray as $key => $menu) {
            $menuArray[$key]['menuitem']['submenu'] = array_values($menu['menuitem']['submenu']);
        }

        return array_values($menuArray);
    }

    private static function buildSubMenu($parentId, $menuItems)
    {
        $submenuArray = [];
        foreach ($menuItems as $menuItem) {
            if ($menuItem->parent === $parentId) {
                if (!self::menuItemExists($submenuArray, $menuItem->id)) {
                    $submenuArray[] = [
                        'subid' => $menuItem->id,
                        'sublabel' => $menuItem->menu_name,
                        'subroute' => $menuItem->route,
                        'leading' => explode(',', $menuItem->leading),
                        'action' => $menuItem->action,
                        'ability' => $menuItem->ability,
                        'submenu' => self::buildSubMenu($menuItem->id, $menuItems)
                    ];
                }
            }
        }
        return $submenuArray;
    }

    private static function menuItemExists($menuArray, $id)
    {
        foreach ($menuArray as $menuItem) {
            if ($menuItem['subid'] == $id) {
                return true;
            }
        }
        return false;
    }


}

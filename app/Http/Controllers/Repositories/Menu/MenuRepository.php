<?php

namespace App\Http\Controllers\Repositories\Menu;

use App\Http\Controllers\Controller;
use App\Models\M_MasterMenu;
use App\Models\M_MasterUserAccessMenu;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class MenuRepository implements MenuRepositoryInterface
{

    protected $menuEntity;
    protected $accessMenuEntity;

    function __construct(M_MasterMenu $menuEntity, M_MasterUserAccessMenu $accessMenuEntity)
    {
        $this->menuEntity = $menuEntity;
        $this->accessMenuEntity = $accessMenuEntity;
    }

    function getListActiveMenu()
    {
        $query = $this->menuEntity::whereNull('deleted_by')
            ->orWhere('deleted_by', '')
            ->where('status', 'active')
            ->orderBy('menu_name', 'asc')
            ->get();

        return $query;
    }

    function findActiveMenu($id)
    {
        return $this->menuEntity::where('id', $id)
            ->where(function ($query) {
                $query->whereNull('deleted_by')
                    ->orWhere('deleted_by', '');
            })
            ->where('status', 'Active')
            ->where(function ($query) {
                $query->whereNull('parent')
                    ->orWhere('parent', '');
            })
            ->first();
    }

    function findMenuByName($name)
    {
        return $this->menuEntity::where('menu_name', $name)->first();
    }

    function findMenuByRoute($route)
    {
        return $this->menuEntity::where('route', $route)->first();
    }

    function getListAccessMenuByUserId($request)
    {
        $userId = $request->user()->id;

        $query = M_MasterUserAccessMenu::with(['masterMenu' => function ($query) {
            $query->where('status', 'active')
                ->orderBy('order', 'asc')
                ->orderBy('menu_name', 'asc');
        }])
            ->where('users_id', $userId)
            ->get();

        return $query;
    }

    function create($request)
    {
        $getName = $request->menu_name;
        $getRoute = $request->route;

        $menuByName = $this->findMenuByName($getName);

        if ($menuByName) {
            throw new Exception("Menu Name Is Exist", 404);
        }

        $menuByRoute = $this->findMenuByRoute($getRoute);

        if ($menuByRoute) {
            throw new Exception("Route Name Is Exist", 404);
        }

        $data = [
            'menu_name' => $getName ?? '',
            'route' => $getRoute ?? '',
            'order' => $request->order ?? 0,
            'leading' => $request->leading ?? '',
            'action' => $request->action ?? '',
            'status' => 'active',
            'created_by' => $request->user()->id ?? ''
        ];

        if ($request->parent == "") {
            $data['parent'] = 0;
        } else {
            $data['parent'] = $request->parent;
        }

        return $this->menuEntity::create($data);
    }

    function update($request, $menuId)
    {
        $getName = $request->menu_name;

        $findActiveMenu = $this->findActiveMenu($menuId);

        if (!$findActiveMenu) {
            throw new Exception("Menu Id Not Found", 404);
        }

        $menuByName = $this->findMenuByName($getName);

        $cekId = $findActiveMenu->id != $menuId;

        if ($menuByName && $cekId) {
            throw new Exception("Menu Name Is Exist", 404);
        }

        $data = [
            'menu_name' => $request->menu_name,
            'updated_by' => $request->user()->id ?? '',
            'updated_at' => Carbon::now('Asia/Jakarta')
        ];

        return $findActiveMenu->update($data);
    }

    function delete($request, $menuId)
    {
        $findActiveMenu = $this->findActiveMenu($menuId);

        if (!$findActiveMenu) {
            throw new Exception("Menu Id Not Found", 404);
        }

        $data = [
            'deleted_by' => $request->user()->id,
            'deleted_at' =>  Carbon::now('Asia/Jakarta')
        ];

        return $findActiveMenu->update($data);
    }

    function getListAccessMenuUser($request)
    {
        $getlistMenu = $this->getListAccessMenuByUserId($request);

        $menuArray = [];
        $homeParent = null;

        foreach ($getlistMenu as $menuItem) {
            $getMenu = $menuItem['masterMenu'];

            if ($getMenu !== null) {
                if ($getMenu['menu_name'] === 'home' && $getMenu['parent'] === null) {
                    $homeParent = $getMenu;
                    break;
                }
            }
        }

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

        foreach ($getlistMenu as $listMenu) {

            $menuItem = $listMenu['masterMenu'];

            if ($menuItem !== null) {
                if ($menuItem['parent'] === null || $menuItem['parent'] === 0) {
                    if (!isset($menuArray[$menuItem['id']])) {
                        $menuArray[$menuItem['id']] = [
                            'menuid' => $menuItem['id'],
                            'menuitem' => [
                                'labelmenu' => $menuItem['menu_name'],
                                'routename' => $menuItem['route'],
                                'leading' => explode(',', $menuItem['leading']),
                                'action' => $menuItem['action'],
                                'ability' => $menuItem['ability'],
                                'submenu' => $this->buildSubMenu($menuItem['id'], $menuItem)
                            ]
                        ];
                    }
                } else {
                    if (!isset($menuArray[$menuItem['parent']])) {
                        $parentMenuItem = $this->findActiveMenu($menuItem['parent']);
                        if ($parentMenuItem) {
                            $menuArray[$menuItem['parent']] = [
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

                    if (isset($menuArray[$menuItem['parent']])) {
                        if (!$this->menuItemExists($menuArray[$menuItem['parent']]['menuitem']['submenu'], $menuItem['id'])) {
                            $menuArray[$menuItem['parent']]['menuitem']['submenu'][] = [
                                'subid' => $menuItem['id'],
                                'sublabel' => $menuItem['menu_name'],
                                'subroute' => $menuItem['route'],
                                'leading' => explode(',', $menuItem['leading']),
                                'action' => $menuItem['action'],
                                'ability' => $menuItem['ability'],
                                'submenu' => $this->buildSubMenu($menuItem['id'], $menuItem)
                            ];
                        }
                    }
                }
            }
        }

        foreach ($menuArray as $key => $menu) {
            $menuArray[$key]['menuitem']['submenu'] = array_values($menu['menuitem']['submenu']);
        }

        usort($menuArray, function ($a, $b) {
            return strcmp(strtolower($a['menuitem']['labelmenu']), strtolower($b['menuitem']['labelmenu']));
        });

        return array_values($menuArray);
    }

    private function buildSubMenu($parentId, $menuItems)
    {
        $submenuArray = [];

        if (is_array($menuItems)) {
            foreach ($menuItems as $menuItem) {
                if (isset($menuItem['parent']) && $menuItem['parent'] === $parentId) {
                    if (!$this->menuItemExists($submenuArray, $menuItem['id'])) {
                        $submenuArray[] = [
                            'subid' => $menuItem['id'],
                            'sublabel' => $menuItem['menu_name'],
                            'subroute' => $menuItem['route'],
                            'leading' => explode(',', $menuItem['leading']),
                            'action' => $menuItem['action'],
                            'ability' => $menuItem['ability'],
                            'submenu' => $this->buildSubMenu($menuItem['id'], $menuItems)
                        ];
                    }
                }
            }
        }

        return $submenuArray;
    }

    private function menuItemExists($menuArray, $id)
    {
        foreach ($menuArray as $menuItem) {
            if ($menuItem['subid'] == $id) {
                return true;
            }
        }
        return false;
    }
}

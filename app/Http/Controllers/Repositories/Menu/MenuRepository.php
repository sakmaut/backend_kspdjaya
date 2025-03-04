<?php

namespace App\Http\Controllers\Repositories\Menu;

use App\Http\Controllers\Controller;
use App\Models\M_MasterMenu;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class MenuRepository implements MenuRepositoryInterface
{

    protected $menuEntity;

    function __construct(M_MasterMenu $menuEntity)
    {
        $this->menuEntity = $menuEntity;
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
        $checkActiveMenu = $this->menuEntity::where('id', $id)
            ->whereNull('deleted_by')
            ->orWhere('deleted_by', '')
            ->where('status', 'active')
            ->first();

        if (!$checkActiveMenu) {
            throw new Exception("Menu Id Not Found", 404);
        }

        return $checkActiveMenu;
    }

    function findMenuByName($name)
    {
        return $this->menuEntity::where('menu_name', $name)->first();
    }

    function findMenuByRoute($route)
    {
        return $this->menuEntity::where('route', $route)->first();
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
            'updated_at' => Carbon::now()
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
            'deleted_at' =>  Carbon::now()
        ];

        return $findActiveMenu->update($data);
    }
}

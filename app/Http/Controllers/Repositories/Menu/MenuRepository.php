<?php

namespace App\Http\Controllers\Repositories\Menu;

use App\Http\Controllers\Controller;
use App\Models\M_MasterMenu;
use Illuminate\Http\Request;

class MenuRepository implements MenuRepositoryInterface
{

    protected $menuEntity;

    function __construct(M_MasterMenu $menuEntity)
    {
        $this->menuEntity = $menuEntity;
    }

    function getActiveMenu()
    {
        $query = $this->menuEntity::whereNull('deleted_by')
            ->orWhere('deleted_by', '')
            ->orderBy('menu_name', 'asc')
            ->get();

        return $query;
    }
}

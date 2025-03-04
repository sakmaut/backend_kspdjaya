<?php

namespace App\Http\Controllers\Repositories\Menu;

interface MenuRepositoryInterface
{
    function getListActiveMenu();
    function findActiveMenu($id);
    function findMenuByName($name);
    function findMenuByRoute($route);
    function create($request);
    function update($request, $menuId);
    function delete($request, $menuId);
}

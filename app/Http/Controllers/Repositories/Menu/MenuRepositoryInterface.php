<?php

namespace App\Http\Controllers\Repositories\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

interface MenuRepositoryInterface
{
    function getActiveMenu();
}

<?php

namespace App\Http\Controllers;

use App\Models\M_HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;


    function createAutoCode($table, $field, $prefix)
    {
        $query = $table::max($field);
        $_trans = date("Ymd");

        $noUrut = (int) substr(!empty($query) ? $query : 0, 17, 5);

        $noUrut++;
        $generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05s", $noUrut);

        return $generateCode;
    }
}

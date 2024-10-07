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
    
        // Ambil urutan dari hasil query
        $noUrut = !empty($query) ? (int) substr($query, 13, 5) : 0; // Ambil angka dari hasil maksimum
        $noUrut++; // Increment tanpa padding
    
        // Hasil akhir dengan padding
        $generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05d", $noUrut);
    
        return $generateCode;
    }
    
}

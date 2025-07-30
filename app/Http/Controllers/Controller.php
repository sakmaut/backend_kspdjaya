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
        // Get the maximum value of the specified field
        $query = $table::max($field);
        $_trans = date("Ymd");

        // Calculate the length of the prefix for dynamic substring extraction
        $prefixLength = strlen($prefix);
        
        // Determine the position to start extracting the numeric part
        $startPos = $prefixLength + 11; // 11 = length of '/YYYYMMDD/'

        // Extract and increment the number
        $noUrut = !empty($query) ? (int) substr($query, $startPos, 5) : 0; // Extract the numeric part
        $noUrut++; // Increment the number

        // Generate the final code with padding
        $generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05d", $noUrut);

        return $generateCode;
    }
    
}

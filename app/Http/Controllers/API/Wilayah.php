<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Wilayah;
use Illuminate\Http\Request;

class Wilayah extends Controller
{

    public function index()
    {
        $query = M_Wilayah::whereRaw('LENGTH(code) = 2')->get();
        return $query;
    }
}

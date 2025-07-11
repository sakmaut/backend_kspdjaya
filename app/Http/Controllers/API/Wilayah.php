<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Wilayah;
use Illuminate\Http\Request;

class Wilayah extends Controller
{
    public function provinsi()
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 2')->get();
        return $query;
    }
}

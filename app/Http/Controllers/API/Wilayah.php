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

    public function kota(Request $request)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 5')->where('kode', 'like', '%' . $request->kode . '%')->get();
        return $query;
    }

    public function kelurahan(Request $request)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 8')->where('kode', 'like', '%' . $request->kode . '%')->get();
        return $query;
    }

    public function kecamatan(Request $request)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 11')->where('kode', 'like', '%' . $request->kode . '%')->get();
        return $query;
    }
}

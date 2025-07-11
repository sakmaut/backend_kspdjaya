<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Wilayah;
use App\Models\M_KodePos;
use App\Models\M_Wilayah;
use Illuminate\Http\Request;

class Wilayah extends Controller
{
    public function provinsi()
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 2')->get();

        $data = R_Wilayah::collection($query);

        return \response()->json($data);
    }

    public function kota($id)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 5')->where('kode', 'like', '%' . $id . '%')->get();
        $data = R_Wilayah::collection($query);

        return \response()->json($data);
    }

    public function kecamatan($id)
    {
        // $query = M_Wilayah::whereRaw('LENGTH(kode) = 8')->where('kode', 'like', '%' . 11.05 . '%')->get();
        // $data = R_Wilayah::collection($query);

        return \response()->json("aaaa");
    }

    public function kelurahan($id)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 13')->where('kode', 'like', '%' . $id . '%')->get();
        $data = R_Wilayah::collection($query);

        return \response()->json($data);
    }

    public function kode_pos($id)
    {
        $query = M_KodePos::where('kode', $id)->get();

        return \response()->json($query);
    }
}

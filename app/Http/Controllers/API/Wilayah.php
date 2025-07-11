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

    public function kecamatan(Request $request)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 8')->where('kode', 'like', '%' . $request->id . '%')->get();
        $data = R_Wilayah::collection($query);

        return \response()->json($data);
    }

    public function kelurahan(Request $request)
    {
        $query = M_Wilayah::whereRaw('LENGTH(kode) = 8')->where('kode', 'like', '%' . $request->id . '%')->get();
        $data = R_Wilayah::collection($query);

        return \response()->json($data);
    }

    public function kode_pos(Request $request)
    {
        $query = M_KodePos::where('kode', $request->id)->get();

        return \response()->json($query);
    }
}

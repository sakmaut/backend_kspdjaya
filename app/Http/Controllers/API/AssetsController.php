<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Validator as FacadesValidator;
use Illuminate\Support\Facades\Storage;

class AssetsController extends Controller
{
    public function storeAsset(Request $request)
    {
        $validator = FacadesValidator::make($request->all(), [
            'file' => 'required|mimes:doc,docx,pdf,txt,csv,jpg,jpeg,png,svg|max:51200',
        ]);

        if ($validator->fails()) {

            return response()->json(['error' => $validator->errors()], 401);
        }


        if ($file = $request->file('file')) {


            $path = $file->store('public/assets');
            $name = $file->getClientOriginalName();
            $link = Storage::url($path);
            $full_link = secure_url($link);

            return response()->json([
                "success" => true,
                "message" => "uploaded",
                "link" => $full_link
            ]);
        }
    }
}

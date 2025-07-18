<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_SavingDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid as Uuid;

class S_SavingDocument extends R_SavingDocument
{
    protected $repository;

    function __construct(R_SavingDocument $repository)
    {
        $this->repository = $repository;
    }

    public function uploaded(Request $request)
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $request->image, $matches)) {
            throw new Exception("Invalid image format", 400);
        }

        $extension = strtolower($matches[1]);
        $imageData = base64_decode(substr($request->image, strpos($request->image, ',') + 1));

        $folderPath = 'saving_document';
        $fileName = Uuid::uuid7()->toString() . '.' . $extension;

        $storagePath = Storage::put("public/" . "$folderPath" . "/{$fileName}", $imageData);
        $publicPath = URL::to('/') . "/storage/" . "$folderPath" . "/" . $fileName;

        $fields = [
            'ID' => $request->id,
            'TYPE' => $request->type,
            'PATH' => $publicPath,
            'CREATED_BY' => $request->user()->fullname,
        ];

        $this->repository->createOrDelete($fields, $request->id);

        return $publicPath;
    }
}

<?php

namespace App\Http\Credit\TagihanDocument\Service;

use App\Http\Credit\TagihanDocument\Repository\R_TagihanDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class S_TagihanDocument extends R_TagihanDocument
{
    protected $repository;

    public function __construct(R_TagihanDocument $repository)
    {
        $this->repository = $repository;
    }

    public function uploadImage($request)
    {
        $baseStorageFolder = 'tagihan_documents';
        $storageDisk = 'public';
        $fullStoragePath = "{$storageDisk}/{$baseStorageFolder}";

        // Cek dan decode base64 image
        if (!preg_match('/^data:image\/(\w+);base64,/', $request->image, $matches)) {
            return response()->json([
                'message' => 'Invalid image format',
                'status' => 400,
            ], 400);
        }

        $extension = strtolower($matches[1]);
        $base64Data = substr($request->image, strpos($request->image, ',') + 1);
        $decodedImage = base64_decode($base64Data);

        if ($decodedImage === false) {
            return response()->json([
                'message' => 'Failed to decode image',
                'status' => 400,
            ], 400);
        }

        // Generate unique filename
        $fileName = Uuid::uuid4()->toString() . '.' . $extension;
        $storagePath = "{$fullStoragePath}/{$fileName}";

        // Simpan file ke storage
        Storage::put($storagePath, $decodedImage);

        // URL file untuk diakses
        $publicUrl = asset(Storage::url("{$baseStorageFolder}/{$fileName}"));

        // Data untuk insert ke DB
        $documentData = [
            'ID' => Uuid::uuid4()->toString(),
            'TAGIHAN_ID' => $request->tagihan_id,
            'ORDER' => 0,
            'PATH' => $publicUrl
        ];

        $this->repository->create($documentData);

        return $publicUrl;
    }
}

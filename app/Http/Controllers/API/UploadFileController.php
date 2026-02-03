<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class UploadFileController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->validate($request, [
                'folder' => 'required|string'
            ]);

            $folder = trim($request->folder);
            $folder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $folder);

            if (empty($folder)) {
                throw new \Exception('Folder tidak valid');
            }

            $storagePath = "public/{$folder}";

            if (!Storage::exists($storagePath)) {
                Storage::makeDirectory($storagePath);
            }

            if ($request->hasFile('image')) {

                $file = $request->file('image');

                if (!$file->isValid()) {
                    return response()->json(['message' => 'File upload tidak valid', "status" => 400], 400);
                }

                $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                $extension  = strtolower($file->getClientOriginalExtension());

                if (!in_array($extension, $allowedExt)) {
                    return response()->json(['message' => 'Format file tidak didukung', "status" => 400], 400);
                }

                $fileName = Uuid::uuid7()->toString() . '.' . $extension;

                Storage::putFileAs($storagePath, $file, $fileName);
            }elseif (
                is_string($request->image) &&
                preg_match('/^data:image\/(png|jpg|jpeg|gif|webp);base64,/', $request->image, $type)
            ) {

                $data = substr($request->image, strpos($request->image, ',') + 1);
                $data = base64_decode($data);

                if ($data === false) {
                    throw new \Exception('Gagal decode base64 image');
                }

                $extension = strtolower($type[1]);
                $fileName  = Uuid::uuid7()->toString() . '.' . $extension;

                Storage::put("{$storagePath}/{$fileName}", $data);
            }else {
                return response()->json(['message' => 'Image harus berupa file upload atau base64', "status" => 400], 400);
            }

            $url = asset("storage/{$folder}/{$fileName}");

            DB::commit();
            return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

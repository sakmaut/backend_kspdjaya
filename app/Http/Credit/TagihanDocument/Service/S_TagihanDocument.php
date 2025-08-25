<?php

namespace App\Http\Credit\TagihanDocument\Service;

use App\Http\Credit\TagihanDocument\Repository\R_TagihanDocument;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;

class S_TagihanDocument extends R_TagihanDocument
{
    protected $repository;

    public function __construct(R_TagihanDocument $repository)
    {
        $this->repository = $repository;
    }

    public function uploadImage(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'cr_prospect_id' => 'required|string'
            ]);

            // Decode the base64 string
            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Survey/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $fileSize = strlen($data);
                $fileSizeInKB = floor($fileSize / 1024);
                // Adjust path

                // Create the URL for the stored image
                $url = URL::to('/') . '/storage/' . 'Cr_Survey/' . $fileName;

                // Prepare data for database insertion
                $data_array_attachment = [
                    'ID' => Uuid::uuid4()->toString(),
                    'CR_SURVEY_ID' => $req->cr_prospect_id,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => isset($req->reff) ? $req->reff : '',
                    'PATH' => $url ?? '',
                    'SIZE' => $fileSizeInKB . ' kb',
                    'CREATED_BY' => $req->user()->fullname,
                    'TIMEMILISECOND' => round(microtime(true) * 1000)
                ];

                // Insert the record into the database
                M_CrSurveyDocument::create($data_array_attachment);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $req);
        }
    }
}

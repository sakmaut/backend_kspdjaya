<?php

namespace App\Http\Credit\TagihanDocument\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Credit\TagihanDocument\Service\S_TagihanDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_TagihanDocument extends Controller
{

    protected $service;
    protected $log;

    public function __construct(
        S_TagihanDocument $service,
        ExceptionHandling $log
    ) {
        $this->service = $service;
        $this->log = $log;
    }

    public function index()
    {
        // TODO: implement index
    }

    public function show($id)
    {
        // TODO: implement show
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'image' => 'required|string',
                'tagihan_id' => 'required|string',
            ]);

            $data = $this->service->uploadImage($request);

            DB::commit();

            return response()->json([
                'message' => 'Image uploaded successfully',
                'status' => 200,
                'response' => $data,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        // TODO: implement update
    }

    public function destroy($id)
    {
        // TODO: implement destroy
    }
}

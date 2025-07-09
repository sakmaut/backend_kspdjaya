<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_ProductSaving;
use App\Http\Controllers\Saving\Service\S_ProductSaving;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_ProductSaving extends Controller
{
    protected $service;
    protected $log;

    function __construct(S_ProductSaving $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data =  $this->service->getAllDataProductSaving();
            $json = Rs_ProductSaving::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = $this->service->findById($id);
            $json = new Rs_ProductSaving($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->service->createOrUpdate($request);

            DB::commit();
            return response()->json(["message" => "success"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->service->createOrUpdate($request, $id, "update");

            DB::commit();
            return response()->json(["message" => "success"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->service->createOrUpdate($request, $id, "delete");

            DB::commit();
            return response()->json(["message" => "success"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

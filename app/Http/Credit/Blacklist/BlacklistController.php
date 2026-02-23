<?php

namespace App\Http\Credit\Blacklist;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlacklistController extends Controller
{
    protected BlacklistService $service;
    protected $log;

    public function __construct(BlacklistService $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->service->showAll();

            $dto = BlacklistDTO::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $detail = $this->service->findById($id);

            if (!$detail) {
                throw new Exception("Id Not Found", 404);
            }

            $dto = new BlacklistDTO($detail);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->service->create($request);
            DB::commit();
            return response()->json(['message' => 'Created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $detail = $this->service->findById($id);

            if (!$detail) {
                throw new Exception("Id Not Found", 404);
            }

            $this->service->update($request, $id);
            DB::commit();
            return response()->json(['message' => 'Updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->log->logError($e, $request);
        }
    }
}

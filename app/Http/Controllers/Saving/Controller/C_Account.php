<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_Account;
use App\Http\Controllers\Saving\Service\S_Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_Account extends Controller
{
    protected $service;
    protected $log;

    function __construct(S_Account $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data =  $this->service->getAllAccount();
            $json = Rs_Account::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function getByCustCode(Request $request, $custCode)
    {
        try {
            $data =  $this->service->getAllAccountByCustCode($custCode);
            $json = Rs_Account::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = $this->service->findById($id);
            $json = new Rs_Account($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->service->create($request);

            DB::commit();
            return response()->json(["message" => "success", 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $data = $this->service->create($request, $id);

            DB::commit();
            return response()->json(["message" => "success", 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

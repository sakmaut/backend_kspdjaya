<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
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
            // $data =  $this->service->getAllCustomer();
            // $json = Rs_Customers::collection($data);

            return response()->json("asss", 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            // $data = $this->service->findById($id);
            // $json = new Rs_Customers($data);

            return response()->json("lll", 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->service->createOrUpdate($request);

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
            $data = $this->service->createOrUpdate($request, $id);

            DB::commit();
            return response()->json(["message" => "success", 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_SavingTransactionLog;
use App\Http\Controllers\Saving\Service\S_SavingTransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_SavingTransactionLog extends Controller
{
    protected $service;
    protected $log;

    function __construct(S_SavingTransactionLog $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function show(Request $request,$accNumber)
    {
        try {
            $data =  $this->service->findTransactionLogByAccNumber($accNumber);
            // $json = Rs_SavingTransactionLog::collection($data);

            return response()->json($data, 200);
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
}

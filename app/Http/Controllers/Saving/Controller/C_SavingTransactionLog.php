<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_SavingTransactionLog;
use App\Http\Controllers\Saving\Resource\Rs_TransaksiLog;
use App\Http\Controllers\Saving\Service\S_SavingTransactionLog;
use App\Models\M_Saving;
use App\Models\M_SavingLog;
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

    public function index(Request $request)
    {
        try {
            $data = $this->getListData();
            $json = Rs_TransaksiLog::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function findTrxByAcc(Request $request, $id)
    {
        try {
            $data = $this->getListData($id);
            $json = Rs_TransaksiLog::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function getListData($accnum = null)
    {
        $query = M_SavingLog::with(['savings', 'savings.customer', 'user']);

        if (!is_null($accnum)) {
            $query->whereHas('savings', function ($q) use ($accnum) {
                $q->where('ACC_NUM', $accnum);
            });
        }

        $data = $query->get();

        return $data;
    }

    public function show(Request $request, $accNumber)
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
            $data = $this->service->createSaving($request);

            DB::commit();
            return response()->json(["message" => "success", 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

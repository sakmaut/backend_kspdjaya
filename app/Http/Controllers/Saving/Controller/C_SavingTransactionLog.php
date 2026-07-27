<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_SavingTransactionLog;
use App\Http\Controllers\Saving\Resource\Rs_TransaksiLog;
use App\Http\Controllers\Saving\Service\S_SavingTransactionLog;
use App\Models\M_Saving;
use App\Models\M_SavingLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_SavingTransactionLog extends Controller
{
    protected $service;
    protected $log;

    function __construct(
        S_SavingTransactionLog $service,
        ExceptionHandling $log
    ) {
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
            $data = $this->getListDataHarian($id);
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

        $data = $query->orderBy('trx_date', 'asc')->get();

        return $data;
    }

    public function getListDataHarian($accnum = null)
    {
        DB::statement('SET SESSION cte_max_recursion_depth = 100000');

        // 1. Transaksi asli dari saving_log
        $query = M_SavingLog::with(['savings', 'savings.customer', 'user']);

        if (!is_null($accnum)) {
            $query->whereHas('savings', function ($q) use ($accnum) {
                $q->where('ACC_NUM', $accnum);
            });
        }

        $trxData = $query->orderBy('TRX_DATE', 'asc')->get();

        // 2. Data bunga masuk & pajak, langsung dari view
        $bungaQuery = DB::table('v_bunga_harian')
            ->whereIn('jenis', ['BUNGA MASUK', 'PAJAK 20%']);

        if (!is_null($accnum)) {
            $bungaQuery->where('acc_number', $accnum);
        }

        $bungaData = $bungaQuery->orderBy('tanggal', 'asc')->get();

        $bungaAsLog = $bungaData->map(function ($row) {
            $obj = new \stdClass();
            $obj->TRX_DATE     = $row->tanggal;
            $obj->BALANCE      = $row->nominal;
            $obj->TRX_TYPE     = $row->jenis;
            $obj->BOOK         = 1;
            $obj->PAGE         = 1;
            $obj->ROW          = 1;
            $obj->DESCRIPTION  = $row->keterangan;
            $obj->LAST_BALANCE = $row->saldo_sesudah;

            $customer = new \stdClass();
            $customer->NAME = $row->acc_name;

            $savings = new \stdClass();
            $savings->ACC_NUM = $row->acc_number;
            $savings->customer = $customer;
            $obj->savings = $savings;

            $user = new \stdClass();
            $user->fullname = 'SYSTEM';
            $obj->user = $user;

            return $obj;
        });

        $merged = $trxData->concat($bungaAsLog)
            ->sortBy(function ($item) {
                return Carbon::parse($item->TRX_DATE);
            })
            ->values();

        return $merged;
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

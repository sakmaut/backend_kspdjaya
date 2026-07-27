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

        // ===========================
        // 1. Ambil transaksi saving_log
        // ===========================
        $trxQuery = M_SavingLog::with(['savings.customer', 'user']);

        if (!is_null($accnum)) {
            $trxQuery->whereHas('savings', function ($q) use ($accnum) {
                $q->where('ACC_NUM', $accnum);
            });
        }

        $trxData = $trxQuery
            ->orderBy('TRX_DATE')
            ->orderBy('BOOK')
            ->orderBy('PAGE')
            ->orderBy('ROW')
            ->get()
            ->map(function ($item) {
                $item->sort_order = 2; // transaksi normal
                return $item;
            });

        // ===========================
        // 2. Ambil bunga harian
        // ===========================
        $bungaQuery = DB::table('v_bunga_harian')
            ->whereIn('jenis', ['MONTHLY INTEREST', 'TAX 20%']);

        if (!is_null($accnum)) {
            $bungaQuery->where('acc_number', $accnum);
        }

        $bungaData = $bungaQuery
            ->orderBy('tanggal')
            ->get();

        $bungaAsLog = $bungaData->map(function ($row) {

            $obj = new \stdClass();

            $obj->TRX_DATE = $row->tanggal;
            $obj->BOOK = 1;
            $obj->PAGE = 1;
            $obj->ROW = 1;

            $obj->TRX_TYPE = strtoupper($row->jenis);
            $obj->DESCRIPTION = $row->keterangan;

            // nominal disimpan apa adanya
            $obj->BALANCE = (float) $row->nominal;

            // nanti dihitung ulang
            $obj->LAST_BALANCE = 0;

            $customer = new \stdClass();
            $customer->NAME = $row->acc_name;

            $saving = new \stdClass();
            $saving->ACC_NUM = $row->acc_number;
            $saving->customer = $customer;

            $obj->savings = $saving;

            $user = new \stdClass();
            $user->fullname = 'SYSTEM';
            $obj->user = $user;

            // bunga muncul sebelum transaksi manual pada tanggal yang sama
            $obj->sort_order = $row->jenis == 'MONTHLY INTEREST' ? 0 : 1;

            return $obj;
        });

        // ===========================
        // 3. Merge data
        // ===========================
        $merged = $trxData
            ->concat($bungaAsLog)
            ->sort(function ($a, $b) {

                $dateCompare =Carbon::parse($a->TRX_DATE)
                    ->timestamp <=>Carbon::parse($b->TRX_DATE)->timestamp;

                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                // DAILY INTEREST -> TAX -> TRANSAKSI
                if ($a->sort_order != $b->sort_order) {
                    return $a->sort_order <=> $b->sort_order;
                }

                if (($a->BOOK ?? 0) != ($b->BOOK ?? 0)) {
                    return ($a->BOOK ?? 0) <=> ($b->BOOK ?? 0);
                }

                if (($a->PAGE ?? 0) != ($b->PAGE ?? 0)) {
                    return ($a->PAGE ?? 0) <=> ($b->PAGE ?? 0);
                }

                return ($a->ROW ?? 0) <=> ($b->ROW ?? 0);
            })
            ->values();

        // ===========================
        // 4. Hitung ulang saldo berjalan
        // ===========================
        $runningBalance = 0;

        foreach ($merged as $item) {

            $nominal = (float) $item->BALANCE;

            switch (strtoupper($item->TRX_TYPE)) {

                case 'CREDIT':
                case 'MONTHLY INTEREST':
                    $runningBalance += abs($nominal);
                    break;

                case 'DEBET':
                case 'TAX 20%':
                case 'DEBIT':
                    $runningBalance -= abs($nominal);
                    break;

                default:
                    $runningBalance += $nominal;
                    break;
            }

            $item->LAST_BALANCE = round($runningBalance, 2);

            unset($item->sort_order);
        }

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

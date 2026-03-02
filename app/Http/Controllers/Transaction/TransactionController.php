<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    protected $service;
    protected $log;

    public function __construct(
        TransactionServices $service,
        ExceptionHandling $log
    ) {
        $this->service = $service;
        $this->log = $log;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->service->create($request);
            
            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

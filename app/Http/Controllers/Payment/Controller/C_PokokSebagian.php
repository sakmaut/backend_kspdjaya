<?php

namespace App\Http\Controllers\Payment\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\Service\S_PokokSebagian;
use App\Services\Credit\CreditService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_PokokSebagian extends Controller
{
    protected $service;
    protected $log;
    protected $creditService;

    public function __construct(
        S_PokokSebagian $service,
        ExceptionHandling $log
    ) {
        $this->service = $service;
        $this->log = $log;
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $data = $this->service->getAllCreditInstallment($request);

    //         return response()->json($data, 200);
    //     } catch (\Exception $e) {
    //         return $this->log->logError($e, $request);
    //     }
    // }

    public function proccessPayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->service->processPayment($request);

            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

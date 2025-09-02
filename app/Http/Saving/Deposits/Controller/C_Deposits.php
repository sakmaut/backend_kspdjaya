<?php

namespace App\Http\Saving\Deposits\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Saving\Deposits\Resource\Rs_Deposits;
use App\Http\Saving\Deposits\Service\S_Deposits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_Deposits extends Controller
{
    protected $service;
    protected $log;

    function __construct(S_Deposits $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data =  $this->service->getAllDeposits();
            $json = Rs_Deposits::collection($data);

            return response()->json($json, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = $this->service->getDepositByNumber($id);
            // $json = new Rs_Deposits($data);

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $execute = $this->service->createDeposit($request);

            DB::commit();
            return response()->json(["message" => $execute], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        // TODO: implement update
    }

    public function destroy($id)
    {
        // TODO: implement destroy
    }
}

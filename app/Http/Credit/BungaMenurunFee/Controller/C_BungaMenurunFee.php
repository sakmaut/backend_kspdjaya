<?php

namespace App\Http\Credit\BungaMenurunFee\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Credit\BungaMenurunFee\Rs_BungaMenurunFee;
use App\Http\Credit\BungaMenurunFee\Service\S_BungaMenurunFee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_BungaMenurunFee extends Controller
{

    protected $service;
    protected $log;

    public function __construct(ExceptionHandling $log,S_BungaMenurunFee $service)
    {
        $this->log = $log;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $results = $this->service->showAll();

            $dto = Rs_BungaMenurunFee::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $result = $this->service->findByid($id);

            if (!$result) {
                throw new Exception("Id Not Found", 404);
            }

            $dto = new Rs_BungaMenurunFee($result);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'Plafond' => 'required'
            ]);

           $exceute = $this->service->createOrUpdate($request);

            DB::commit();
            return response()->json($exceute, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        // TODO: implement update
    }
}

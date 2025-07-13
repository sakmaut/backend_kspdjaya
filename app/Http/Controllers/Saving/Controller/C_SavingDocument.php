<?php

namespace App\Http\Controllers\Saving\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Resource\Rs_Customers;
use App\Http\Controllers\Saving\Service\S_Customers;
use App\Http\Controllers\Saving\Service\S_SavingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class C_SavingDocument extends Controller
{
    protected $service;
    protected $log;

    function __construct(S_SavingDocument $service, ExceptionHandling $log)
    {
        $this->service = $service;
        $this->log = $log;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'image' => 'required|string',
                'id' => 'required|string',
                'type' => 'nullable|string',
            ]);

            $data = $this->service->uploaded($request);    

            DB::commit();
            return response()->json([
                'message' => 'success',
                'status' => 200,
                'response' => $data
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

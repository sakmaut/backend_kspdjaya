<?php

namespace App\Http\Controllers\Ticketing\TicketingMessages;


use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingMessagesController extends Controller
{
    protected $services;
    protected $log;

    public function __construct(TicketingMessagesService $services, ExceptionHandling $log)
    {
        $this->services = $services;
        $this->log = $log;
    }

    public function show(Request $request, $id)
    {
        try {
            $list = $this->services->getDetailByTicketId($id);

            $dto = TicketingMessagesDTO::collection($list);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->services->create($request);

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

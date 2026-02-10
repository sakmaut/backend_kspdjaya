<?php

namespace App\Http\Controllers\Ticketing;


use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingController extends Controller
{
    protected $ticketingService;
    protected $log;

    public function __construct(TicketingService $ticketingService, ExceptionHandling $log)
    {
        $this->ticketingService = $ticketingService;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->ticketingService->getAllData($request);

            $dto = TicketingDTO::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $detail = $this->ticketingService->getDetailById($id);

            if (!$detail) {
                throw new Exception("Id Not Found", 404);
            }

            $dto = new TicketingDTO($detail);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->ticketingService->create($request);

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $this->ticketingService->assignHandler($request, $id);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function UpdateToClosedTicket(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->ticketingService->updateToClosedTicketByPic($request);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

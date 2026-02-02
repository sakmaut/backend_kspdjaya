<?php

namespace App\Http\Controllers\Ticketing;


use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Branch\BranchRepository;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
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
            $data = $this->ticketingService->getAllData();

            $dto = TicketingDTO::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $detail = $this->ticketingService->findById($id);

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

    // public function update(Request $request, $id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $request->validate([
    //             'CODE' => 'unique:branch,code,' . $id,
    //             'NAME' => 'unique:branch,name,' . $id
    //         ]);

    //         $this->branchRepository->update($request, $id);

    //         DB::commit();
    //         return response()->json(['message' => 'Cabang updated successfully'], 200);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return $this->log->logError($e, $request);
    //     }
    // }

    // public function destroy(Request $request, $id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $this->branchRepository->delete($request, $id);

    //         DB::commit();
    //         return response()->json(['message' => 'deleted successfully'], 200);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return $this->log->logError($e, $request);
    //     }
    // }
}

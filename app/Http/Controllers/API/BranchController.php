<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Branch\BranchRepository;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    protected $branchRepository;
    protected $log;

    public function __construct(BranchRepository $branchRepository, ExceptionHandling $log)
    {
        $this->branchRepository = $branchRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getAllBranch = $this->branchRepository->getActiveBranch();

            $dto = R_Branch::collection($getAllBranch);

            return response()->json(['message' => 'OK', 'response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $branchById = $this->branchRepository->findBranchById($id);

            if (!$branchById) {
                throw new Exception("Branch Id Not Found", 404);
            }

            $dto = new R_BranchDetail($branchById);

            return response()->json(['message' => 'OK', 'response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->validate($request, [
                'CODE' => 'required|string',
                'NAME' => 'required|string',
                'ADDRESS' => 'required|string'
            ], [
                'CODE.required' => 'Kode Wajib Diisi',
                'NAME.required' => 'Nama Wajib Diisi',
                'ADDRESS.required' => 'Alamat Wajib Diisi'
            ]);

            $this->branchRepository->create($request);

            DB::commit();
            return response()->json(['message' => 'branch created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'CODE' => 'unique:branch,code,' . $id,
                'NAME' => 'unique:branch,name,' . $id,
                'ADDRESS' => 'required|string',
                'ZIP_CODE' => 'numeric'
            ]);

            $this->branchRepository->update($request, $id);

            DB::commit();
            return response()->json(['message' => 'Cabang updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->branchRepository->delete($request, $id);

            DB::commit();
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

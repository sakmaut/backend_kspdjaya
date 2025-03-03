<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Branch\BranchRepository;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
use App\Models\M_Branch;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
                throw new Exception("Branch Not Found", 404);
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

            $getCode = $request->CODE;
            $getName = $request->NAME;

            $this->validate($request, [
                'CODE' => 'required|string',
                'NAME' => 'required|string',
                'ADDRESS' => 'required|string'
            ], [
                'CODE.required' => 'Kode Wajib Diisi',
                'NAME.required' => 'Nama Wajib Diisi',
                'ADDRESS.required' => 'Alamat Wajib Diisi'
            ]);

            $branchByCode = $this->branchRepository->findBranchByCode($getCode);

            if ($branchByCode) {
                throw new Exception("Code Branch Is Exist", 404);
            }

            $branchByName = $this->branchRepository->findBranchByCode($getName);

            if ($branchByName) {
                throw new Exception("Code Name Is Exist", 404);
            }

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

            $branch = M_Branch::findOrFail($id);

            $request['MOD_USER'] = $request->user()->id;
            $request['MOD_DATE'] = Carbon::now()->format('Y-m-d');

            $data = array_change_key_case($request->all(), CASE_UPPER);

            compareData(M_Branch::class, $id, $data, $request);

            $branch->update($data);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Cabang updated successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroy(Request $req, $id)
    {
        DB::beginTransaction();
        try {

            $users = M_Branch::findOrFail($id);

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $data = array_change_key_case($update, CASE_UPPER);

            $users->update($data);

            DB::commit();
            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'Users deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

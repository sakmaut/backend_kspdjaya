<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Collateral\CollateralRepository;
use App\Http\Resources\R_CrCollateral;
use App\Http\Resources\R_CrCollateralApprovalList;
use App\Models\M_BpkbDetail;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_CrCollateralDocumentRelease;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class CollateralController extends Controller
{

    protected $collateralRepository;
    protected $log;

    public function __construct(CollateralRepository $collateralRepository, ExceptionHandling $log)
    {
        $this->collateralRepository = $collateralRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $searchCollateralList = $this->collateralRepository->searchCollateralList($request);

            $dto = R_CrCollateral::collection($searchCollateralList);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $findCollateralById = $this->collateralRepository->findCollateralById($id);

            if (!$findCollateralById) {
                throw new Exception('Collateral Id Not Found', 404);
            }

            $dto = new R_CrCollateral($findCollateralById);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->collateralRepository->update($request, $id);

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function collateral_status(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->collateralRepository->collateral_status($request);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function uploadImage(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'collateral_id' => 'required|string'
            ]);

            // Decode the base64 string
            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Collateral/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . 'Cr_Collateral/' . $fileName;

                $collateral = [
                    'COLLATERAL_ID' => $req->collateral_id,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => round(microtime(true) * 1000),
                    'PATH' => $url ?? ''
                ];

                M_CrCollateralDocument::create($collateral);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function uploadImageRelease(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'uid' => 'required|string'
            ]);

            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid7()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Collateral_Release/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . 'Cr_Collateral_Release/' . $fileName;

                $collateral = [
                    'COLLATERAL_ID' => $req->uid,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => round(microtime(true) * 1000),
                    'PATH' => $url ?? '',
                    'CREATED_BY' => $req->user()->id ?? '',
                    'CREATED_AT' => Carbon::now('Asia/Jakarta') ?? null
                ];

                M_CrCollateralDocumentRelease::create($collateral);

                $checkCollateral = M_CrCollateral::where('ID', $req->uid)->first();

                if ($checkCollateral) {
                    $checkCollateral->update(['STATUS' => 'RILIS']);
                }

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided'], 400);
            }
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function collateralApprovalList(Request $request)
    {
        try {
            $getListData = $this->collateralRepository->getAllCollateralApprovalList();

            $dto = R_CrCollateralApprovalList::collection($getListData);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function collateralApproval(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'request_id' => 'required|string',
                'flag_approval' => 'required|string'
            ]);

            $this->collateralRepository->collateralApproval($request);

            DB::commit();
            return response()->json(["message" => "success"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

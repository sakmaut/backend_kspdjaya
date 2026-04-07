<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_SurveyVisum;
use App\Models\M_CrSurveyVisum;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrSurveyVisumController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $userId = $user->id;
            $branchId = $user->branch_id;
            $position = $user->position;

            $query = M_CrSurveyVisum::with([
                'user',
                'branch'
            ]);

            if (in_array($position, ['KAPOS', 'ADMIN'])) {
                $query->where('created_branch', $branchId);
            } elseif (in_array($position, ['MCF', 'KOLEKTOR'])) {
                $query->where('created_by', $userId);
            }

            $data = $query->orderBy('created_at', 'desc')->get();

            $dto = R_SurveyVisum::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = M_CrSurveyVisum::with([
                'user',
                'branch'
            ])->where('id', $id)->first();

            if (!$data) {
                throw new Exception("Data Not Found", 404);
            }

            $dto = new R_SurveyVisum($data);

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
                'Id' => 'required|string',
                'Nama' => 'required|string'
            ]);

            $fields =[
                'id' => $request->Id ?? '',
                'status_konsumen' => $request->Status ?? '',
                'nama_konsumen' => $request->Nama ?? '',
                'alamat_konsumen' => $request->Alamat ?? '',
                'no_handphone' => $request->NoHandphone ?? '',
                'status_konsumen' => $request->Status ?? '',
                'hasil_followup' => $request->HasilFollowup ?? '',
                'sumber_order' => $request->SumberOrder ?? '',
                'keterangan' => $request->Keterangan ?? '',
                'path' => $request->Path ?? '',
                'created_by' => $request->user()->id ?? '',
                'created_branch' => $request->user()->branch_id ?? '',
            ];

            M_CrSurveyVisum::create($fields);

            DB::commit();

            return response()->json([
                'message' => 'Survey Visum created successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

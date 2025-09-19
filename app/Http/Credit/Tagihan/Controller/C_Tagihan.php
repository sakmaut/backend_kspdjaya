<?php

namespace App\Http\Credit\Tagihan\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Credit\Tagihan\Service\S_Tagihan;
use App\Http\Resources\R_TagihanDetail;
use App\Http\Resources\Rs_TagihanByUserId;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class C_Tagihan extends Controller
{

    protected $service;
    protected $log;

    public function __construct(
        S_Tagihan $service,
        ExceptionHandling $log
    ) {
        $this->service = $service;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->service->getListTagihan($request);

            $dto = R_TagihanDetail::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function listTagihanByUserId(Request $request)
    {
        try {
            $data = $this->service->listTagihanByUserId($request);

            $dto = Rs_TagihanByUserId::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show($id)
    {
        // TODO: implement show
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,username',
            'list_tagihan' => 'required|array|min:1',
            'list_tagihan.*.NO KONTRAK' => 'required|string',
            'list_tagihan.*.TGL BOOKING' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $this->service->createTagihan($request);

            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,username',
            'list_tagihan' => 'required|array|min:1',
            'list_tagihan.*.NO KONTRAK' => 'required|string',
            'list_tagihan.*.TGL BOOKING' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $this->service->createTagihan($request, $id);

            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

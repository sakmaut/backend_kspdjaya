<?php

namespace App\Http\Credit\Tagihan\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Service\S_Tagihan;
use App\Http\Resources\R_TagihanDetail;
use App\Http\Resources\Rs_CollectorList;
use App\Http\Resources\Rs_DeployList;
use App\Http\Resources\Rs_LkpDetailList;
use App\Http\Resources\Rs_LkpList;
use App\Http\Resources\Rs_LkpPicList;
use App\Http\Resources\Rs_TagihanByUserId;
use App\Models\M_ClSurveyLogs;
use App\Models\M_Lkp;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

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

    public function list_tagihan_collector(Request $request)
    {
        try {
            $userId = $request->user()->username ?? null;

            if (!$userId) {
                throw new \Exception("User ID not found.", 500);
            }

            $subQuery = DB::table('payment')
                ->selectRaw('SUM(ORIGINAL_AMOUNT) AS total_bayar, LOAN_NUM')
                ->whereMonth('ENTRY_DATE', Carbon::now()->month)
                ->whereYear('ENTRY_DATE', Carbon::now()->year)
                ->groupBy('LOAN_NUM');

            $data = DB::table('cl_deploy as a')
                ->leftJoin('cl_lkp_detail as b', 'b.NO_SURAT', '=', 'a.NO_SURAT')
                ->leftJoin('cl_lkp as c', 'c.ID', '=', 'b.LKP_ID')
                ->leftJoinSub($subQuery, 'd', function ($join) {
                    $join->on('d.LOAN_NUM', '=', 'a.LOAN_NUMBER');
                })
                ->where('a.USER_ID', $userId)
                ->select('a.*', 'c.*', 'd.total_bayar')
                ->orderByRaw('c.LKP_NUMBER IS NOT NULL DESC')
                ->get();

            $dto = Rs_CollectorList::collection($data);

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

    public function cl_deploy_list(Request $request)
    {
        try {
            $data = $this->service->listTagihanByBranchId($request);

            $dto = Rs_DeployList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_logs(Request $request, $id)
    {
        try {
            $data = DB::table('cl_logs')
                ->where('reference', $id)
                ->get();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_deploy_by_pic(Request $request, $pic)
    {
        try {
            // $data = $this->service->cl_deploy_by_pic($pic);

            $subQuery = DB::table('payment')
                ->selectRaw('SUM(ORIGINAL_AMOUNT) AS total_bayar, LOAN_NUM')
                ->whereMonth('ENTRY_DATE', Carbon::now()->month)
                ->whereYear('ENTRY_DATE', Carbon::now()->year)
                ->groupBy('LOAN_NUM');

            // Query utama
            $data = DB::table('cl_deploy as a')
                ->leftJoin('cl_lkp_detail as b', 'b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                ->leftJoin('cl_lkp as c', 'c.ID', '=', 'b.LKP_ID')
                ->leftJoinSub($subQuery, 'd', function ($join) {
                    $join->on('d.LOAN_NUM', '=', 'a.LOAN_NUMBER');
                })
                ->where('a.USER_ID', $pic)
                ->where(function ($query) {
                    $query->whereNull('c.LKP_NUMBER')
                        ->orWhere('c.LKP_NUMBER', '');
                })
                ->select('a.*', 'c.*', 'd.total_bayar')
                ->get();

            $dto = Rs_LkpPicList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,username',
            'list_tagihan' => 'required|array|min:1',
            'list_tagihan.*.NO KONTRAK' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!is_string($value) && !is_numeric($value)) {
                        $fail($attribute . ' harus berupa string atau angka.');
                    }
                },
            ],
            'list_tagihan.*.TGL BOOKING' => 'required|date',
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

    public function cl_lkp_add(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,username'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $this->service->createLkp($request);

            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function cl_lkp_list(Request $request)
    {
        try {
            $data = M_Lkp::join('users', 'cl_lkp.USER_ID', '=', 'users.username')
                ->with('user')
                ->orderBy('users.fullname', 'ASC')
                ->select('cl_lkp.*')
                ->get();

            $dto = Rs_LkpList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_lkp_detail(Request $request, $id)
    {
        try {
            $data = M_Lkp::with('detail')->where('LKP_NUMBER', $id)->first();

            $dto = new Rs_LkpDetailList($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_survey_add(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = M_ClSurveyLogs::create([
                'REFERENCE_ID' => $request->no_surat ?? "",
                'DESCRIPTION' => $request->keterangan,
                'CONFIRM_DATE' => Carbon::parse($request->tgl_jb)->format('Y-m-d') ?? null,
                'PATH' => json_encode($request->path),
                'CREATED_BY' => $request->user()->id ?? null,
            ]);

            DB::commit();
            return response()->json($data, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function cl_survey_detail(Request $request, $id)
    {
        try {
            $data = M_ClSurveyLogs::where('REFERENCE_ID', $id)->get();
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_survey_upload(Request $req)
    {
        DB::beginTransaction();
        try {

            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Tagihan/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . 'Tagihan/' . $fileName;

                DB::commit();
                return response()->json(['response' => $url], 200);
            } else {
                DB::rollback();
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $req);
        }
    }
}

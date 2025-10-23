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
use App\Http\Resources\Rs_SurveyLogs;
use App\Http\Resources\Rs_TagihanByUserId;
use App\Models\M_ClSurveyLogs;
use App\Models\M_ListbanData;
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
            $currentBranch = $request->user()->branch_id;
            $currentPosition = $request->user()->position;

            if ($currentPosition != 'HO') {
                $cycles = ['CM', 'C8', 'C7', 'C6', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0'];
            } else {
                $cycles = ['CM', 'CX', 'C8', 'C7', 'C6', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0'];
            }

            $query = DB::table('listban_data as a')
                ->select(
                    'a.*',
                    'c.NAME',
                    'c.INS_ADDRESS',
                    'c.INS_KECAMATAN',
                    'c.INS_KELURAHAN',
                    'c.PHONE_HOUSE',
                    'c.PHONE_PERSONAL',
                    'c.OCCUPATION',
                    'd.ID as DEPLOY_ID',
                    'd.LOAN_NUMBER',
                    'd.STATUS as DEPLOY_STATUS'
                )
                ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoin('cl_deploy as d', function ($join) {
                    $join->on('d.LOAN_NUMBER', '=', 'a.NO_KONTRAK')
                        ->where('d.STATUS', '=', 'AKTIF');
                })
                ->whereIn('a.CYCLE_AWAL', $cycles)
                ->whereNull('d.LOAN_NUMBER');

            if ($currentPosition != 'HO') {
                $query->where('a.BRANCH_ID', $currentBranch);
            }

            $data = $query->get();

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

            $subQuery = DB::table('payment as p')
                ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
                ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
                ->whereMonth('p.ENTRY_DATE', now()->month)
                ->whereYear('p.ENTRY_DATE', now()->year)
                ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
                ->groupBy('p.LOAN_NUM');

            $logSubQuery = DB::table('cl_survey_logs')
                ->select('REFERENCE_ID', 'DESCRIPTION', 'CONFIRM_DATE')
                ->whereDate('CREATED_AT', now()->toDateString())
                ->orderBy('CREATED_AT', 'desc')
                ->limit(1);

            $totalDenda = DB::table('arrears')
                ->where('STATUS_REC', 'A')
                ->selectRaw('LOAN_NUMBER, (SUM(PAST_DUE_PENALTY) - SUM(PAID_PENALTY)) AS total_denda')
                ->groupBy('LOAN_NUMBER');

            $lkpSubQuery = DB::table('cl_lkp_detail as b')
                ->leftJoin('cl_lkp as c', 'c.ID', '=', 'b.LKP_ID')
                ->where('c.STATUS', 'Active')
                ->select('b.*', 'c.LKP_NUMBER');

            $data = DB::table('cl_deploy as a')
                ->leftJoinSub($lkpSubQuery, 'b', function ($join) {
                    $join->on('b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER');
                })
                ->leftJoin('cl_lkp as c', function ($join) {
                    $join->on('c.ID', '=', 'b.LKP_ID')
                        ->where('c.STATUS', '=', 'Active');
                })
                ->leftJoinSub($subQuery, 'd', function ($join) {
                    $join->on('d.LOAN_NUM', '=', 'a.LOAN_NUMBER');
                })
                ->leftJoinSub($logSubQuery, 'e', function ($join) {
                    $join->on('e.REFERENCE_ID', '=', 'a.NO_SURAT');
                })
                ->leftJoinSub($totalDenda, 'g', function ($join) {
                    $join->on('g.LOAN_NUMBER', '=', 'a.LOAN_NUMBER');
                })
                ->leftJoin('customer as f', 'f.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoin('cr_collateral as cc', 'cc.CR_CREDIT_ID', '=', 'a.CREDIT_ID')
                ->where('a.USER_ID', $userId)
                ->select(
                    'a.*',
                    'c.*',
                    'd.total_bayar',
                    'e.DESCRIPTION',
                    'e.CONFIRM_DATE',
                    'f.NAME',
                    'f.INS_ADDRESS',
                    'f.INS_KECAMATAN',
                    'f.INS_KELURAHAN',
                    'f.PHONE_HOUSE',
                    'f.PHONE_PERSONAl',
                    'g.total_denda',
                    DB::raw("CONCAT(cc.BRAND, ' - ', cc.TYPE, ' - ', cc.COLOR) AS unit"),
                    'cc.POLICE_NUMBER',
                    'cc.PRODUCTION_YEAR'
                )
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

    public function cl_deploy_update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_Tagihan::where('ID', $id)->first();

            if ($check) {
                $check->update([
                    'USER_ID' => $request->user_id ?? "",
                    'UPDATED_BY' => $request->user()->id ?? null,
                    'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Cabang updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function cl_deploy_delete(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_Tagihan::where('ID', $id)->first();

            if ($check) {
                $check->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Cabang updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function cl_logs(Request $request, $id)
    {
        try {
            $data = DB::table('cl_logs')
                ->where('reference', $id)
                ->orderBy('create_date', 'desc')
                ->get();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cl_deploy_by_pic(Request $request, $pic)
    {
        try {
            $subQuery = DB::table('payment as p')
                ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
                ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
                ->whereMonth('p.ENTRY_DATE', now()->month)
                ->whereYear('p.ENTRY_DATE', now()->year)
                ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
                ->groupBy('p.LOAN_NUM');

            // Subquery log survei
            $logSubQuery = DB::table('cl_survey_logs')
                ->select('REFERENCE_ID', 'DESCRIPTION', 'CONFIRM_DATE')
                ->orderBy('CREATED_AT', 'desc')
                ->limit(1);

            $lkpSubQuery = DB::table('cl_lkp_detail as b')
                ->leftJoin('cl_lkp as c', 'c.ID', '=', 'b.LKP_ID')
                ->where('c.STATUS', 'Active')
                ->select('b.*', 'c.LKP_NUMBER');

            // Query utama
            $data = DB::table('cl_deploy as a')
                ->leftJoinSub($lkpSubQuery, 'bc', function ($join) {
                    $join->on('bc.LOAN_NUMBER', '=', 'a.LOAN_NUMBER');
                })
                ->leftJoin('customer as cust', 'cust.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoinSub($subQuery, 'pay', function ($join) {
                    $join->on('pay.LOAN_NUM', '=', 'a.LOAN_NUMBER');
                })
                ->leftJoinSub($logSubQuery, 'e', function ($join) {
                    $join->on('e.REFERENCE_ID', '=', 'a.NO_SURAT');
                })
                ->where('a.USER_ID', $pic)
                ->whereRaw('a.ANGSURAN > COALESCE(pay.total_bayar, 0)')
                ->where(function ($query) {
                    $query->whereNull('bc.LKP_NUMBER')
                        ->orWhere('bc.LKP_NUMBER', '');
                })
                ->select(
                    'a.ID',
                    'a.NO_SURAT',
                    'a.USER_ID',
                    'a.BRANCH_ID',
                    'a.CREDIT_ID',
                    'a.LOAN_NUMBER',
                    'a.CUST_CODE',
                    'a.TGL_JTH_TEMPO',
                    'a.CYCLE_AWAL',
                    'a.N_BOT',
                    'a.MCF',
                    'a.ANGSURAN_KE',
                    'a.ANGSURAN',
                    DB::raw('COALESCE(pay.total_bayar, 0) AS total_bayar'),
                    'e.DESCRIPTION',
                    'e.CONFIRM_DATE',
                    'cust.NAME AS NAMA_CUST',
                    'cust.ADDRESS AS ALAMAT',
                    'cust.KECAMATAN AS KEC',
                    'cust.KELURAHAN AS DESA'
                )
                ->orderBy('pay.total_bayar', 'asc')
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
            ]
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
                ->where('cl_lkp.STATUS', 'Active')
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
                'CONFIRM_DATE' => $request->tgl_jb
                    ? Carbon::createFromTimestamp($request->tgl_jb / 1000)->format('Y-m-d')
                    : null,
                'PATH' => json_encode($request->path),
                'CREATED_BY' => $request->user()->id ?? null,
                'CREATED_AT' => Carbon::now('Asia/Jakarta'),
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
            $data = M_ClSurveyLogs::where('REFERENCE_ID', $id)->orderBy('CREATED_AT', 'DESC')->get();

            $dto = Rs_SurveyLogs::collection($data);

            return response()->json($dto, 200);
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

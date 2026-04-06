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
use App\Models\M_CollateralView;
use App\Models\M_ListbanData;
use App\Models\M_Lkp;
use App\Models\M_LkpProgress;
use App\Models\TableViews\M_ColllectorVisits;
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
                    'd.STATUS as DEPLOY_STATUS',
                    'e.POSITION',
                    DB::raw('COALESCE(e.keterangan, "RESIGN") as STATUS_MCF')
                )
                ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoin('cl_deploy as d', function ($join) {
                    $join->on('d.LOAN_NUMBER', '=', 'a.NO_KONTRAK')
                        ->where('d.STATUS', '=', 'AKTIF')
                        ->whereMonth('d.CREATED_AT', now()->month)
                        ->whereYear('d.CREATED_AT', now()->year);
                })
                ->leftJoin('users as e', 'e.id', '=', 'a.SURVEYOR_ID')
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
            $user = $request->user();

            $userId   = $user->username;
            $branchId = $user->branch_id;
            $position = strtoupper($user->position);

            $query = M_ColllectorVisits::with('collateralDocuments');

            if (in_array($position, ['KAPOS', 'ADMIN'])) {
                $query->where('BRANCH_ID', $branchId);
            }

            if (in_array($position, ['MCF', 'KOLEKTOR'])) {
                $query->where('USER_ID', $userId);
            }

            return response()->json(Rs_CollectorList::collection($query->get()),200);
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

    public function updateBatch(Request $request)
    {
        DB::beginTransaction();
        try {

            if (empty($request->id) || count($request->id) == 0) {
                return response()->json([
                    'message' => 'ID tidak boleh kosong'
                ], 400);
            }

            M_Tagihan::whereIn('ID', $request->id)
                ->update([
                    'USER_ID' => $request->user_id,
                    'UPDATED_BY' => $request->user()->id ?? null,
                    'UPDATED_AT' => now()
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Batch update success'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function deleteBatch(Request $request)
    {
        DB::beginTransaction();
        try {

            if (empty($request->id) || count($request->id) == 0) {
                return response()->json([
                    'message' => 'ID tidak boleh kosong'
                ], 400);
            }

            M_Tagihan::whereIn('ID', $request->id)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Batch delete success'
            ], 200);
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

    public function ClDeployBatch(Request $request, $id)
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

    // public function cl_deploy_by_pic(Request $request, $pic)
    // {
    //     try {

    //         $checkValidate = M_LkpProgress::where('Petugas', $pic)
    //             ->where('Status', 'OPEN')
    //             ->count();

    //         $subQuery = DB::table('payment as p')
    //             ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
    //             ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
    //             ->whereMonth('p.ENTRY_DATE', now()->month)
    //             ->whereYear('p.ENTRY_DATE', now()->year)
    //             ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
    //             ->groupBy('p.LOAN_NUM');

    //         $logSubQuery = DB::table('cl_survey_logs as t')
    //             ->join(
    //                 DB::raw('(SELECT REFERENCE_ID, MAX(CREATED_AT) AS max_created 
    //               FROM cl_survey_logs 
    //               GROUP BY REFERENCE_ID) x'),
    //                 function ($join) {
    //                     $join->on('x.REFERENCE_ID', '=', 't.REFERENCE_ID')
    //                         ->on('x.max_created', '=', 't.CREATED_AT');
    //                 }
    //             )
    //             ->select('t.REFERENCE_ID', 't.DESCRIPTION', 't.CONFIRM_DATE');

    //         $lkpSubQuery = DB::table('cl_lkp as c')
    //             ->leftJoin('cl_lkp_detail as b', 'b.LKP_ID', '=', 'c.ID')
    //             ->leftJoin(DB::raw("
    //             (
    //                 SELECT DISTINCT s1.REFERENCE_ID, s1.LKP_NUMBER
    //                 FROM cl_survey_logs s1
    //                 INNER JOIN (
    //                     SELECT REFERENCE_ID, LKP_NUMBER, MAX(CREATED_AT) AS max_created
    //                     FROM cl_survey_logs
    //                     GROUP BY REFERENCE_ID, LKP_NUMBER
    //                 ) s2
    //                 ON s1.REFERENCE_ID = s2.REFERENCE_ID
    //                 AND s1.LKP_NUMBER = s2.LKP_NUMBER
    //                 AND s1.CREATED_AT = s2.max_created
    //             ) survey
    //         "), function ($join) {
    //                 $join->on('survey.REFERENCE_ID', '=', 'b.NO_SURAT')
    //                     ->on('survey.LKP_NUMBER', '=', 'c.LKP_NUMBER');
    //             })
    //             ->where('c.STATUS', '!=', 'Draft')
    //             ->groupBy('b.LOAN_NUMBER', 'c.LKP_NUMBER', 'c.ID')
    //             ->havingRaw('COUNT(DISTINCT b.NO_SURAT) > COUNT(DISTINCT survey.REFERENCE_ID)')
    //             ->select('b.LOAN_NUMBER', 'c.LKP_NUMBER');

    //         // Query utama
    //         $data = DB::table('cl_deploy as a')
    //             ->leftJoinSub($lkpSubQuery, 'bc', function ($join) {
    //                 $join->on('bc.LOAN_NUMBER', '=', 'a.LOAN_NUMBER');
    //             })
    //             ->leftJoin('customer as cust', 'cust.CUST_CODE', '=', 'a.CUST_CODE')
    //             ->leftJoin('credit as cr', 'cr.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
    //             ->leftJoinSub($subQuery, 'pay', function ($join) {
    //                 $join->on('pay.LOAN_NUM', '=', 'a.LOAN_NUMBER');
    //             })
    //             ->leftJoinSub($logSubQuery, 'e', function ($join) {
    //                 $join->on('e.REFERENCE_ID', '=', 'a.NO_SURAT');
    //             })
    //             ->where('a.USER_ID', $pic)
    //             ->whereRaw('a.AMBC_TOTAL_AWAL > COALESCE(pay.total_bayar, 0)')
    //             ->where('cr.STATUS_REC', 'AC')
    //             ->whereMonth('a.CREATED_AT', now()->month)
    //             ->whereYear('a.CREATED_AT', now()->year)
    //             ->where(function ($query) {
    //                 $query->whereNull('bc.LKP_NUMBER')
    //                     ->orWhere('bc.LKP_NUMBER', '');
    //             })
    //             ->select(
    //                 'a.ID',
    //                 'a.NO_SURAT',
    //                 'a.USER_ID',
    //                 'a.BRANCH_ID',
    //                 'a.CREDIT_ID',
    //                 'a.LOAN_NUMBER',
    //                 'a.CUST_CODE',
    //                 'a.TGL_JTH_TEMPO',
    //                 'a.CYCLE_AWAL',
    //                 'a.N_BOT',
    //                 'a.MCF',
    //                 'a.ANGSURAN_KE',
    //                 'a.ANGSURAN',
    //                 'a.AMBC_TOTAL_AWAL',
    //                 DB::raw('COALESCE(pay.total_bayar, 0) AS total_bayar'),
    //                 'e.DESCRIPTION',
    //                 'e.CONFIRM_DATE',
    //                 'cust.NAME AS NAMA_CUST',
    //                 'cust.ADDRESS AS ALAMAT',
    //                 'cust.KECAMATAN AS KEC',
    //                 'cust.KELURAHAN AS DESA'
    //             )
    //             ->orderBy('a.TGL_JTH_TEMPO', 'asc')
    //             ->get();

    //         $dto = Rs_LkpPicList::collection($data);

    //         return response()->json([
    //             "AddLkp" => $checkValidate >= 3 ? false : true,
    //             "list" => $dto
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return $this->log->logError($e, $request);
    //     }
    // }

    public function cl_deploy_by_pic(Request $request, $pic)
    {
        try {

            $checkValidate = M_LkpProgress::where('Petugas', $pic)
                ->where('Status', 'OPEN')
                ->count();

            // $subQuery = DB::table('payment as p')
            //     ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
            //     ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
            //     ->whereMonth('p.ENTRY_DATE', now()->month)
            //     ->whereYear('p.ENTRY_DATE', now()->year)
            //     ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
            //     ->groupBy('p.LOAN_NUM');

            $lkpSubQuery = DB::table('cl_lkp as c')
                ->leftJoin('cl_lkp_detail as b', 'b.LKP_ID', '=', 'c.ID')
                ->leftJoin(DB::raw("
                (
                    SELECT DISTINCT s1.REFERENCE_ID, s1.LKP_NUMBER
                    FROM cl_survey_logs s1
                    INNER JOIN (
                        SELECT REFERENCE_ID, LKP_NUMBER, MAX(CREATED_AT) AS max_created
                        FROM cl_survey_logs
                        GROUP BY REFERENCE_ID, LKP_NUMBER
                    ) s2
                    ON s1.REFERENCE_ID = s2.REFERENCE_ID
                    AND s1.LKP_NUMBER = s2.LKP_NUMBER
                    AND s1.CREATED_AT = s2.max_created
                ) survey
            "), function ($join) {
                    $join->on('survey.REFERENCE_ID', '=', 'b.NO_SURAT')
                        ->on('survey.LKP_NUMBER', '=', 'c.LKP_NUMBER');
                })
                ->where('c.STATUS', '!=', 'Draft')
                ->groupBy('b.LOAN_NUMBER', 'c.LKP_NUMBER', 'c.ID')
                ->havingRaw('COUNT(DISTINCT b.NO_SURAT) > COUNT(DISTINCT survey.REFERENCE_ID)')
                ->select('b.LOAN_NUMBER', 'c.LKP_NUMBER');

            $data = M_Tagihan::with([
                    'assignUser:username,fullname',
                    'customer:CUST_CODE,NAME,INS_ADDRESS,INS_KECAMATAN,INS_KELURAHAN',
                    'credit:LOAN_NUMBER,STATUS_REC',
                    'surveyLogs' => function ($q) {
                        $q->select(
                            'cl_survey_logs.REFERENCE_ID',
                            'cl_survey_logs.DESCRIPTION',
                            'cl_survey_logs.CONFIRM_DATE',
                            'cl_survey_logs.CREATED_AT'
                        );
                    }
                ])
                ->withSum(['paymentDetails as total_bayar' => function ($q) {
                    $q->whereIn('ACC_KEYS', [
                        'BAYAR_POKOK',
                        'BAYAR_BUNGA',
                        'ANGSURAN_POKOK',
                        'ANGSURAN_BUNGA'
                    ])
                        ->whereMonth('payment.ENTRY_DATE', now()->month)
                        ->whereYear('payment.ENTRY_DATE', now()->year);
                }], 'ORIGINAL_AMOUNT')
                ->leftJoinSub($lkpSubQuery, 'bc', function ($join) {
                    $join->on('bc.LOAN_NUMBER', '=', 'cl_deploy.LOAN_NUMBER');
                })
                ->where('cl_deploy.USER_ID', $pic)
                ->whereHas('credit', function ($q) {
                    $q->where('STATUS_REC', 'AC');
                })
                ->whereRaw('cl_deploy.AMBC_TOTAL_AWAL > COALESCE(total_bayar,0)')
                ->whereMonth('cl_deploy.CREATED_AT', now()->month)
                ->whereYear('cl_deploy.CREATED_AT', now()->year)
                ->where(function ($query) {
                    $query->whereNull('bc.LKP_NUMBER')
                        ->orWhere('bc.LKP_NUMBER', '');
                })
                ->orderBy('cl_deploy.TGL_JTH_TEMPO', 'asc')
                ->select(
                    'cl_deploy.ID',
                    'cl_deploy.NO_SURAT',
                    'cl_deploy.USER_ID',
                    'cl_deploy.BRANCH_ID',
                    'cl_deploy.CREDIT_ID',
                    'cl_deploy.LOAN_NUMBER',
                    'cl_deploy.CUST_CODE',
                    'cl_deploy.TGL_JTH_TEMPO',
                    'cl_deploy.CYCLE_AWAL',
                    'cl_deploy.N_BOT',
                    'cl_deploy.MCF',
                    'cl_deploy.ANGSURAN_KE',
                    'cl_deploy.ANGSURAN',
                    'cl_deploy.AMBC_TOTAL_AWAL'
                )
                ->get();

            $dto = Rs_LkpPicList::collection($data);

            return response()->json([
                "AddLkp" => $checkValidate >= 3 ? false : true,
                "list" => $dto
            ], 200);
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

    public function cl_lkp_edit(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,username',
                'LkpId' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $this->service->updateLkp($request);

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

            $currentBranch = $request->user()->branch_id;
            $currentPosition = $request->user()->position;

            $month = Carbon::now()->month;
            $year  = Carbon::now()->year;

            $query = M_LkpProgress::query()
                ->whereMonth('Tanggal', $month)
                ->whereYear('Tanggal', $year);

            if ($currentPosition !== 'HO') {
                $query->where('CABANG_ID', $currentBranch);
            }

            $data = $query
                ->orderByRaw('DATE(Tanggal) DESC')
                ->orderBy('NamaPetugas', 'ASC')
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
            $data = M_Lkp::with([
                'detail.deploy',
                'detail.surveyLogs' => function ($q) use ($id) {
                    $q->where('LKP_NUMBER', $id);
                },
                'user:username,fullname',
                'detail.payments' => function ($q) {
                    $q->whereMonth('payment.ENTRY_DATE', now()->month)
                        ->whereYear('payment.ENTRY_DATE', now()->year);
                },
                'detail.payments.details' => function ($q) {
                    $q->whereIn('ACC_KEYS', [
                        'BAYAR_POKOK',
                        'BAYAR_BUNGA',
                        'ANGSURAN_POKOK',
                        'ANGSURAN_BUNGA',
                    ]);
                },
                ])
                ->where('LKP_NUMBER', $id)
                ->first();

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
                "LKP_NUMBER" => $request->lkp_number ?? "",
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

                $extension = strtolower($type[1]);
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

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

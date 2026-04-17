<?php

namespace App\Http\Credit\Tagihan\Controller;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Service\S_Tagihan;
use App\Http\Resources\R_TagihanDetail;
use App\Http\Resources\Rs_CollectorDetail;
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
use App\Models\TableViews\M_ColllectorList;
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

            $query = DB::table('cl_deploy as a')
                ->select([
                    'a.ID',
                    'a.NO_SURAT',
                    'a.CUST_CODE',
                    'a.CREDIT_ID',
                    'a.TENOR',
                    'f.NAME',
                    'lkp.LKP_NUMBER',
                    'a.LOAN_NUMBER',
                    'a.TGL_JTH_TEMPO',
                    'a.USER_ID',
                    'a.BRANCH_ID',
                    'a.ANGSURAN_KE',
                    'a.ANGSURAN',
                    'f.INS_ADDRESS',
                    'cls.DESCRIPTION',
                    DB::raw('cls.CREATED_AT as SURVEY_DATE'),
                    DB::raw('cls.CONFIRM_DATE as CONFIRM_DATE'),
                    'cls.PATH',
                    'br.NAME as nama_cabang',
                    'us.fullname as pic',
                    'a.CYCLE_AWAL',
                    'a.CYCLE_AKHIR',
                    'd.ENTRY_DATE',
                    'd.total_bayar'
                ])
                ->leftJoin('customer as f', 'f.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoin('branch as br', 'br.ID', '=', 'a.BRANCH_ID')
                ->leftJoin('users as us', 'us.USERNAME', '=', 'a.USER_ID')
                ->leftJoin('vw_lkp as lkp', 'lkp.NO_SURAT', '=', 'a.NO_SURAT')
                ->leftJoin('vw_survey_logs as cls', function ($join) {
                    $join->on('cls.REFERENCE_ID', '=', 'a.NO_SURAT')
                        ->on('cls.LKP_NUMBER', '=', 'lkp.LKP_NUMBER');
                })
                ->leftJoin('vw_payment as d', 'd.LOAN_NUM', '=', 'a.LOAN_NUMBER')
                ->whereRaw("a.CREATED_AT >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")
                ->whereRaw("a.CREATED_AT < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')");

            if (in_array($position, ['KAPOS', 'ADMIN'])) {
                $query->where('a.BRANCH_ID', $branchId);
            }

            if (in_array($position, ['MCF', 'KOLEKTOR'])) {
                $query->where('a.USER_ID', $userId);
            }

            $result = $query->orderByRaw('(lkp.LKP_NUMBER IS NULL) ASC')->get();

            return response()->json(Rs_CollectorList::collection($result), 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function list_tagihan_collector_detail(Request $request, $id)
    {
        try {
            // $query = M_ColllectorVisits::with(['collateralDocuments', 'credit.cr_application.cr_survey_document'])->where('ID', $id)->first();

            $query = DB::table('vw_tagihan_list as v')
                    ->select([
                        'v.*',
                        'g.total_denda',
                        DB::raw("CONCAT(cc.BRAND,' - ',cc.TYPE,' - ',cc.COLOR) AS unit"),
                        'cc.ID as COLLATERAL_ID',
                        'cc.POLICE_NUMBER',
                        'cc.PRODUCTION_YEAR',
                        'f.INS_KECAMATAN',
                        'f.INS_KELURAHAN',
                        'f.PHONE_HOUSE',
                        'f.PHONE_PERSONAL',
                        DB::raw("(
                            SELECT JSON_ARRAYAGG(cd.PATH)
                            FROM cr_collateral_document cd
                            WHERE cd.COLLATERAL_ID = cc.ID
                        ) as col_path"),
                        DB::raw("(
                            SELECT JSON_ARRAYAGG(sd.PATH)
                            FROM cr_survey_document sd
                            JOIN cr_application ca ON ca.CR_SURVEY_ID = sd.CR_SURVEY_ID
                            JOIN credit c ON c.ORDER_NUMBER = ca.ORDER_NUMBER
                            WHERE c.ID = v.CREDIT_ID
                            AND sd.TYPE = 'other'
                        ) as other_path")
                        ])
                        ->leftJoin('customer as f', 'f.CUST_CODE', '=', 'v.CUST_CODE')
                        ->leftJoin('cr_collateral as cc', 'cc.CR_CREDIT_ID', '=', 'v.CREDIT_ID')
                        ->leftJoin(
                        DB::raw("(
                            SELECT
                                LOAN_NUMBER,
                                SUM(PAST_DUE_PENALTY) - SUM(PAID_PENALTY) AS total_denda
                            FROM arrears
                            WHERE STATUS_REC = 'A'
                            GROUP BY LOAN_NUMBER) g"), 'g.LOAN_NUMBER', '=', 'v.LOAN_NUMBER')
                    ->where('v.ID', $id)
                    ->first();

            if (!$query) {
                throw new Exception("Data Not Found", 404);
            }

            return response()->json(new Rs_CollectorDetail($query), 200);
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

            $subQuery = DB::table('payment as p')
                ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
                ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
                ->whereMonth('p.ENTRY_DATE', now()->month)
                ->whereYear('p.ENTRY_DATE', now()->year)
                ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
                ->groupBy('p.LOAN_NUM');

            // $lkpSubQuery = DB::table('cl_lkp as c')
            //     ->leftJoin('cl_lkp_detail as b', 'b.LKP_ID', '=', 'c.ID')
            //     ->leftJoin(DB::raw("
            //     (
            //         SELECT DISTINCT s1.REFERENCE_ID, s1.LKP_NUMBER
            //         FROM cl_survey_logs s1
            //         INNER JOIN (
            //             SELECT REFERENCE_ID, LKP_NUMBER, MAX(CREATED_AT) AS max_created
            //             FROM cl_survey_logs
            //             GROUP BY REFERENCE_ID, LKP_NUMBER
            //         ) s2
            //         ON s1.REFERENCE_ID = s2.REFERENCE_ID
            //         AND s1.LKP_NUMBER = s2.LKP_NUMBER
            //         AND s1.CREATED_AT = s2.max_created
            //     ) survey
            // "), function ($join) {
            //         $join->on('survey.REFERENCE_ID', '=', 'b.NO_SURAT')
            //             ->on('survey.LKP_NUMBER', '=', 'c.LKP_NUMBER');
            //     })
            //     ->where('c.STATUS', '!=', 'Draft')
            //     ->groupBy('b.LOAN_NUMBER', 'c.LKP_NUMBER', 'c.ID')
            //     ->havingRaw('COUNT(DISTINCT b.NO_SURAT) > COUNT(DISTINCT survey.REFERENCE_ID)')
            //     ->select('b.LOAN_NUMBER', 'c.LKP_NUMBER');

            $lkpSubQuery = DB::table('v_lkp_progress as v')
                ->join('cl_lkp_detail as ld', 'ld.LKP_ID', '=', 'v.LKP_ID')
                ->joinSub(
                    DB::table('v_lkp_progress as v')
                        ->join('cl_lkp_detail as ld', 'ld.LKP_ID', '=', 'v.LKP_ID')
                        ->where('v.STATUS', 'OPEN')
                        ->groupBy('ld.LOAN_NUMBER')
                        ->select(
                            'ld.LOAN_NUMBER',
                            DB::raw('MAX(v.NoLkp) AS max_lkp')
                        ),
                    'x',
                    function ($join) {
                        $join->on('x.LOAN_NUMBER', '=', 'ld.LOAN_NUMBER')
                            ->on('x.max_lkp', '=', 'v.NoLkp');
                    }
                )
                ->where('v.STATUS', 'OPEN')
                ->select(
                    'v.NoLkp as LKP_NUMBER',
                    'ld.LOAN_NUMBER',
                    'v.STATUS'
                );

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
            ->leftJoinSub($lkpSubQuery, 'bc', function ($join) {
                $join->on('bc.LOAN_NUMBER', '=', 'cl_deploy.LOAN_NUMBER');
            })
            ->leftJoinSub($subQuery, 'pay', function ($join) {
                $join->on('pay.LOAN_NUM', '=', 'cl_deploy.LOAN_NUMBER');
            })
            ->where('cl_deploy.USER_ID', $pic)
            ->whereHas('credit', function ($q) {
                $q->where('STATUS_REC', 'AC');
            })
            ->whereRaw('cl_deploy.AMBC_TOTAL_AWAL > COALESCE(pay.total_bayar, 0)')
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
                'cl_deploy.AMBC_TOTAL_AWAL',
                'bc.LKP_NUMBER',
                DB::raw('COALESCE(pay.total_bayar,0) as total_bayar')
            )
            ->get();

            $dto = Rs_LkpPicList::collection($data);

            $draftLkp = DB::table('cl_lkp')
                ->selectRaw('(LKP_NUMBER IS NOT NULL) as DRAFTED, ID,LKP_NUMBER')
                ->where('USER_ID', $pic)
                ->where('STATUS', 'Draft')
                ->first();

            return response()->json(array_merge(
                ["AddLkp" => $checkValidate >= 3 ? false : true,], 
                (array) $draftLkp, 
                ["list" => $dto]), 200);
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
                'user_id' => 'required|string',
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

            $userFullname = DB::table('users')->where('id', $data->CREATED_BY)->value('fullname');

            $description =
                'Kunjungan oleh ' .
                $userFullname .
                ($data->CONFIRM_DATE
                    ? ' JB pada ' . $data->CONFIRM_DATE
                    : '') .
                ' KET: ' .
                $data->DESCRIPTION;

            DB::table('cl_logs')->insert([
                'idcl_logs'  => Uuid::uuid7()->toString(),
                'reference'  => $data->REFERENCE_ID,
                'description' => $description,
                'create_date' => Carbon::now('Asia/Jakarta'),
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

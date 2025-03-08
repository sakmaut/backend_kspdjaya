<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\CollateralTransaction\CollateralTransactionRepository;
use App\Http\Resources\R_CollateralTransaction;
use App\Models\M_Branch;
use App\Models\M_CollateralView;
use App\Models\M_CrApplication;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_CrSurveyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BpkbController extends Controller
{

    protected $collateralTransactionRepository;
    protected $log;

    public function __construct(CollateralTransactionRepository $collateralTransactionRepository, ExceptionHandling $log)
    {
        $this->collateralTransactionRepository = $collateralTransactionRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $branch = $request->query('branch');
            $branchId = $request->user()->branch_id;
            $position = $request->user()->position;

            $allJaminan = M_CollateralView::where('COLLATERAL_FLAG', empty($branch) ? $branchId : $branch)->get();
            $onDemand = M_CollateralView::where('LOCATION_BRANCH', empty($branch) ? $branchId : $branch)->get();
            $onGoing = M_CollateralView::where(['COLLATERAL_FLAG' => $branchId, 'LOCATION_BRANCH != ' => $branchId ])->get();
            $onProcess = M_CollateralView::where('STATUS', 'SENDING')->get();

            $build = [
                'all' => $allJaminan,
                'ondemand' => $onDemand,
                'onGoing' => $onGoing,
                'onProcess' => $onProcess
            ];
        

            return response()->json($build, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    protected function getPaginationLinks($db)
    {
        $pagination = [
            'current_page' => $db->currentPage(),
            'next_page_url' => $db->nextPageUrl(),
            'previous_page_url' => $db->previousPageUrl(),
            'total' => $db->total(),
            'per_page' => $db->perPage(),
        ];

        return $pagination;
    }

    public function forpostjaminan(Request $request)
    {
        try {
            $showAllCollateralList = $this->collateralTransactionRepository->showAllCollateralListInOriginalBranch($request);
            $dto = R_CollateralTransaction::collection($showAllCollateralList);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function forgetjaminan(Request $request)
    {
        try {

            $branch = $request->user()->branch_id;

            $collateral = M_CrCollateral::where('COLLATERAL_FLAG', $branch)
                ->where('LOCATION_BRANCH', '<>', $branch)
                ->where(function ($query) {
                    $query->whereNull('DELETED_AT')
                        ->orWhere('DELETED_AT', '');
                })->get();

            $collateral_sertificat = M_CrCollateralSertification::where('COLLATERAL_FLAG', $branch)
                ->where('LOCATION', '<>', $branch)
                ->where(function ($query) {
                    $query->whereNull('DELETED_AT')
                        ->orWhere('DELETED_AT', '');
                })->get();

            $data = [];
            foreach ($collateral as $list) {

                $surveyId = DB::table('credit as a')
                    ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                    ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                    ->leftJoin('bpkb_detail as d', 'd.COLLATERAL_ID', '=', 'c.ID')
                    ->select('a.LOAN_NUMBER', 'b.NAME', 'd.STATUS')
                    ->where('a.ID', '=', $list->CR_CREDIT_ID)
                    ->first();

                $asalBranch = M_Branch::find($list->COLLATERAL_FLAG);
                $brachName = M_Branch::find($list->LOCATION_BRANCH);

                $data[] = [
                    "type" => "kendaraan",
                    'nama_debitur' => $surveyId->NAME ?? NULL,
                    'order_number' => $surveyId->LOAN_NUMBER ?? NULL,
                    'no_jaminan' => $list->BPKB_NUMBER ?? NULL,
                    'id' => $list->ID,
                    'status_jaminan' => $surveyId->STATUS ?? 'NORMAL',
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    "asal_lokasi" => $asalBranch->NAME ?? null,
                    "lokasi" => $brachName->NAME ?? $list->LOCATION_BRANCH,
                    "document" => $this->getCollateralDocument($list->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']) ?? null,
                ];
            }

            foreach ($collateral_sertificat as $list) {

                $surveyId = DB::table('credit as a')
                    ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                    ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                    ->leftJoin('bpkb_detail as d', 'd.COLLATERAL_ID', '=', 'c.ID')
                    ->select('a.LOAN_NUMBER', 'b.NAME', 'd.STATUS')
                    ->where('a.ID', '=', $list->CR_CREDIT_ID)
                    ->first();

                $brachName = M_Branch::find($list->LOCATION_BRANCH);

                $data[] = [
                    "type" => "sertifikat",
                    'nama_debitur' => $surveyId->NAME ?? NULL,
                    'order_number' => $surveyId->LOAN_NUMBER ?? NULL,
                    'no_jaminan' => $list->NO_SERTIFIKAT ?? NULL,
                    'id' => $list->ID,
                    'status_jaminan' => $surveyId->STATUS ?? 'NORMAL',
                    "no_sertifikat" => $list->NO_SERTIFIKAT,
                    "status_kepemilikan" => $list->STATUS_KEPEMILIKAN,
                    "imb" => $list->IMB,
                    "luas_tanah" => $list->LUAS_TANAH,
                    "luas_bangunan" => $list->LUAS_BANGUNAN,
                    "lokasi" => $list->LOKASI,
                    "provinsi" => $list->PROVINSI,
                    "kab_kota" => $list->KAB_KOTA,
                    "kec" => $list->KECAMATAN,
                    "desa" => $list->DESA,
                    "atas_nama" => $list->ATAS_NAMA,
                    "nilai" => (int) $list->NILAI,
                    "lokasi" => $brachName->NAME ?? null,
                    "document" => $this->getCollateralDocument($list->ID, ['sertifikat']) ?? null
                ];
            }

            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function attachment_guarante($survey_id, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        return $documents;
    }

    public function attachmentSertifikat($survey_id, $array = [])
    {
        $attachment = M_CrSurveyDocument::where('CR_SURVEY_ID', $survey_id)
            ->whereIn('TYPE', $array)
            ->orderBy('TIMEMILISECOND', 'desc')
            ->get();

        return $attachment;
    }

    public function attachment($collateralId, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_collateral_document_release AS csd
                WHERE (TYPE, COUNTER_ID) IN (
                    SELECT TYPE, MAX(COUNTER_ID)
                    FROM cr_collateral_document_release
                    WHERE TYPE IN ($data)
                        AND COLLATERAL_ID = '$collateralId'
                    GROUP BY TYPE
                )
                ORDER BY COUNTER_ID DESC"
        );

        return $documents;
    }

    function getCollateralDocument($creditID, $param)
    {

        $documents = DB::table('cr_collateral_document')
            ->whereIn('TYPE', $param)
            ->where('COLLATERAL_ID', '=', $creditID)
            ->get();

        return $documents;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Survey\SurveyRepository;
use App\Http\Resources\R_CrProspect;
use App\Http\Resources\R_CrSurvey;
use App\Http\Resources\R_SurveyDetail;
use App\Models\M_CrGuaranteBillyet;
use App\Models\M_CrGuaranteGold;
use App\Models\M_CrGuaranteSertification;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_SurveyApproval;
use App\Models\M_SurveyApprovalLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class CrSurveyController extends Controller
{

    private $CrSurvey;
    private $uuid;
    private $timeNow;
    private $SurveyRepository;
    protected $log;

    public function __construct(M_CrSurvey $CrSurvey, SurveyRepository $SurveyRepository, ExceptionHandling $log)
    {
        $this->CrSurvey = $CrSurvey;
        $this->uuid = Uuid::uuid7()->toString();
        $this->timeNow = Carbon::now();
        $this->SurveyRepository = $SurveyRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getListSurveyByMcf = $this->SurveyRepository->getListSurveyByMcf($request);

            $dto = R_CrSurvey::collection($getListSurveyByMcf);

            return response()->json(['response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $req, $id)
    {
        try {
            $check = $this->CrSurvey->where('id', $id)->whereNull('deleted_at')->firstOrFail();

            // $getListSurveyByMcf = $this->SurveyRepository->getDetailSurvey($id);

            // $dto = R_SurveyDetail::collection($getListSurveyByMcf);

            // return response()->json($dto, 200);

            return response()->json(['message' => 'OK', 'response' => $this->resourceDetail($check)], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function resourceDetail($data)
    {
        $survey_id = $data->id;
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $survey_id)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $guarente_sertificat = M_CrGuaranteSertification::where('CR_SURVEY_ID', $survey_id)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();
        $approval_detail = M_SurveyApproval::where('CR_SURVEY_ID', $survey_id)->first();

        $arrayList = [
            'id' => $survey_id,
            'jenis_angsuran' => $data->jenis_angsuran ?? '',
            'data_order' => [
                'tujuan_kredit' => $data->tujuan_kredit,
                'plafond' => (int) $data->plafond,
                'tenor' => strval($data->tenor),
                'category' => $data->category,
                'jenis_angsuran' => $data->jenis_angsuran
            ],
            'data_nasabah' => [
                'nama' => $data->nama,
                'tgl_lahir' => is_null($data->tgl_lahir) ? null : date('Y-m-d', strtotime($data->tgl_lahir)),
                'no_hp' => $data->hp,
                'no_ktp' => $data->ktp,
                'no_kk' => $data->kk,
                'alamat' => $data->alamat,
                'rt' => $data->rt,
                'rw' => $data->rw,
                'provinsi' => $data->province,
                'kota' => $data->city,
                'kelurahan' => $data->kelurahan,
                'kecamatan' => $data->kecamatan,
                'kode_pos' => $data->zip_code
            ],
            'data_survey' => [
                'usaha' => $data->usaha,
                'sektor' => $data->sector,
                'lama_bekerja' => $data->work_period,
                'pengeluaran' => (int) $data->expenses,
                'pendapatan_pribadi' => (int) $data->income_personal,
                'pendapatan_pasangan' => (int) $data->income_spouse,
                'pendapatan_lainnya' => (int) $data->income_other,
                'tgl_survey' => is_null($data->visit_date) ? null : date('Y-m-d', strtotime($data->visit_date)),
                'catatan_survey' => $data->survey_note,
            ],
            'jaminan' => [],
            'prospect_approval' => [
                'flag_approval' => $approval_detail->ONCHARGE_APPRVL,
                'keterangan' => $approval_detail->ONCHARGE_DESCR,
                'status' => $approval_detail->APPROVAL_RESULT,
                'status_code' => $approval_detail->CODE
            ],
            "dokumen_indentitas" => $this->attachment($survey_id, "'ktp', 'kk', 'ktp_pasangan'"),
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($survey_id, ['other']) ?? null,
        ];

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan'][] = [
                "type" => "kendaraan",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
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
                    "document" => $this->attachment_guarante($survey_id, $list->HEADER_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'")
                ]
            ];
        }

        foreach ($guarente_sertificat as $list) {
            $arrayList['jaminan'][] = [
                "type" => "sertifikat",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
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
                    "document" => M_CrSurveyDocument::attachmentSertifikat($survey_id, $list->HEADER_ID, ['sertifikat']) ?? null,
                ]
            ];
        }

        return $arrayList;
    }

    public function attachment($survey_id, $data)
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

    public function attachment_guarante($survey_id, $header_id, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                        AND COUNTER_ID = '$header_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        return $documents;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'id' => 'required|string|unique:cr_survey',
            ]);

            $check_id_approval =  M_SurveyApproval::where('CR_SURVEY_ID', $request->id)->get();

            if ($check_id_approval->isNotEmpty()) {
                throw new Exception("Id Approval Is Exist", 409);
            }

            if (!empty($request->data_nasabah['dokumen_indentitas'])) {
                foreach ($request->data_nasabah['dokumen_indentitas'] as $list) {
                    $data_array_attachment = [
                        'ID' => Uuid::uuid4()->toString(),
                        'CR_SURVEY_ID' => $request->id,
                        'TYPE' => $list['TYPE'],
                        'COUNTER_ID' => $list['COUNTER_ID'] ?? '',
                        'PATH' => $list['PATH'],
                        'CREATED_BY' => $request->user()->fullname,
                        'TIMEMILISECOND' => round(microtime(true) * 1000)
                    ];

                    M_CrSurveyDocument::create($data_array_attachment);
                }
            }

            $this->createCrSurvey($request);
            $this->createCrProspekApproval($request);

            if (collect($request->jaminan)->isNotEmpty()) {
                $this->insert_guarante($request);
            }

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'created successfully'], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function createCrSurvey($request)
    {
        $data_array = [
            'id' => $request->id,
            'branch_id' => $request->user()->branch_id,
            'visit_date' => isset($request->data_survey['tgl_survey']) && !empty($request->data_survey['tgl_survey']) ? $request->data_survey['tgl_survey'] : null,
            'tujuan_kredit' => $request->order['tujuan_kredit'] ?? null,
            'plafond' => $request->order['plafond'] ?? null,
            'tenor' => $request->order['tenor'] ?? null,
            'category' => $request->order['category'] ?? null,
            'jenis_angsuran' => $request->order['jenis_angsuran'] ?? null,
            'nama' => $request->data_nasabah['nama'] ?? null,
            'tgl_lahir' => $request->data_nasabah['tgl_lahir'] ?? null,
            'ktp' => $request->data_nasabah['no_ktp'] ?? null,
            'kk' => $request->data_nasabah['no_kk'] ?? null,
            'hp' => $request->data_nasabah['no_hp'] ?? null,
            'alamat' => $request->data_nasabah['alamat'] ?? null,
            'rt' => $request->data_nasabah['rt'] ?? null,
            'rw' => $request->data_nasabah['rw'] ?? null,
            'province' => $request->data_nasabah['provinsi'] ?? null,
            'city' => $request->data_nasabah['kota'] ?? null,
            'kecamatan' => $request->data_nasabah['kecamatan'] ?? null,
            'kelurahan' => $request->data_nasabah['kelurahan'] ?? null,
            "work_period" => $request->data_survey['lama_bekerja'] ?? null,
            "income_personal" => $request->data_survey['pendapatan_pribadi'] ?? null,
            "income_spouse" =>  $request->data_survey['pendapatan_pasangan'] ?? null,
            "income_other" =>  $request->data_survey['pendapatan_lainnya'] ?? null,
            'usaha' => $request->data_survey['usaha'] ?? null,
            'sector' => $request->data_survey['sektor'] ?? null,
            "expenses" => $request->data_survey['pengeluaran'] ?? null,
            'survey_note' => $request->data_survey['catatan_survey'] ?? null,
            'created_by' => $request->user()->id
        ];

        M_CrSurvey::create($data_array);
    }

    private function createCrProspekApproval($request)
    {
        $data = [
            'CR_SURVEY_ID' => $request->id
        ];

        if (!$request->flag) {
            $data['CODE'] = 'DRSVY';
            $data['APPROVAL_RESULT'] = 'draf survey';
        } else {
            $data['CODE'] = 'WADM';
            $data['APPROVAL_RESULT'] = 'menunggu admin';
        }

        $approval = M_SurveyApproval::create($data);

        $data_log = [
            'ID' => $this->uuid,
            'CODE' => $data['CODE'],
            'SURVEY_APPROVAL_ID' => $approval->ID,
            'ONCHARGE_APPRVL' => 'AUTO_APPROVED_BY_SYSTEM',
            'ONCHARGE_PERSON' => $request->user()->id,
            'ONCHARGE_TIME' => Carbon::now(),
            'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
            'APPROVAL_RESULT' => $data['APPROVAL_RESULT']
        ];

        M_SurveyApprovalLog::create($data_log);
    }

    private function insert_guarante($request)
    {
        if (!empty($request->jaminan)) {
            foreach ($request->jaminan as $result) {

                switch ($result['type']) {
                    case 'kendaraan':
                        $data_array_col = [
                            'ID' => Uuid::uuid7()->toString(),
                            'CR_SURVEY_ID' => $request->id,
                            'HEADER_ID' => $result['counter_id'],
                            'TYPE' => $result['atr']['tipe'] ?? null,
                            'BRAND' => $result['atr']['merk'] ?? null,
                            'PRODUCTION_YEAR' => $result['atr']['tahun'] ?? null,
                            'COLOR' => $result['atr']['warna'] ?? null,
                            'ON_BEHALF' => $result['atr']['atas_nama'] ?? null,
                            'POLICE_NUMBER' => $result['atr']['no_polisi'] ?? null,
                            'CHASIS_NUMBER' => $result['atr']['no_rangka'] ?? null,
                            'ENGINE_NUMBER' => $result['atr']['no_mesin'] ?? null,
                            'BPKB_NUMBER' => $result['atr']['no_bpkb'] ?? null,
                            'STNK_NUMBER' => $result['atr']['no_stnk'] ?? null,
                            'STNK_VALID_DATE' => $result['atr']['tgl_stnk'] ?? null,
                            'VALUE' => $result['atr']['nilai'] ?? null,
                            'COLLATERAL_FLAG' => "",
                            'VERSION' => 1,
                            'CREATE_DATE' => $this->timeNow,
                            'CREATE_BY' => $request->user()->id,
                        ];

                        M_CrGuaranteVehicle::create($data_array_col);

                        foreach ($request->jaminan as $res) {
                            if (!empty($res['atr']['document']) && isset($res['atr']['document']) && is_array($res['atr']['document'])) {
                                foreach ($res['atr']['document'] as $datas) {
                                    $data_array_attachment = [
                                        'ID' => Uuid::uuid4()->toString(),
                                        'CR_SURVEY_ID' => $request->id,
                                        'TYPE' => $datas['TYPE'],
                                        'COUNTER_ID' => $datas['COUNTER_ID'] ?? '',
                                        'PATH' => $datas['PATH'],
                                        'CREATED_BY' => $request->user()->fullname,
                                        'TIMEMILISECOND' => round(microtime(true) * 1000)
                                    ];

                                    M_CrSurveyDocument::create($data_array_attachment);
                                }
                            }
                        }

                        break;
                    case 'sertifikat':
                        $data_array_col = [
                            'ID' => Uuid::uuid7()->toString(),
                            'HEADER_ID' => $result['counter_id'],
                            'CR_SURVEY_ID' => $request->id,
                            'STATUS_JAMINAN' => $result['atr']['status_jaminan'] ?? null,
                            'NO_SERTIFIKAT' => $result['atr']['no_sertifikat'] ?? null,
                            'STATUS_KEPEMILIKAN' => $result['atr']['status_kepemilikan'] ?? null,
                            'IMB' => $result['atr']['imb'] ?? null,
                            'LUAS_TANAH' => $result['atr']['luas_tanah'] ?? null,
                            'LUAS_BANGUNAN' => $result['atr']['luas_bangunan'] ?? null,
                            'LOKASI' => $result['atr']['lokasi'] ?? null,
                            'PROVINSI' => $result['atr']['provinsi'] ?? null,
                            'KAB_KOTA' => $result['atr']['kab_kota'] ?? null,
                            'KECAMATAN' => $result['atr']['kec'] ?? null,
                            'DESA' => $result['atr']['desa'] ?? null,
                            'ATAS_NAMA' => $result['atr']['atas_nama'] ?? null,
                            'NILAI' => $result['atr']['nilai'] ?? null,
                            'COLLATERAL_FLAG' => "",
                            'VERSION' => 1,
                            'CREATE_DATE' => $this->timeNow,
                            'CREATE_BY' => $request->user()->id,
                        ];

                        M_CrGuaranteSertification::create($data_array_col);

                        foreach ($request->jaminan as $res) {
                            if (!empty($res['atr']['document']) && isset($res['atr']['document']) && is_array($res['atr']['document'])) {
                                foreach ($res['atr']['document'] as $datas) {
                                    $data_array_attachment = [
                                        'ID' => Uuid::uuid4()->toString(),
                                        'CR_SURVEY_ID' => $request->id,
                                        'TYPE' => $datas['TYPE'],
                                        'COUNTER_ID' => $datas['COUNTER_ID'] ?? '',
                                        'PATH' => $datas['PATH'],
                                        'CREATED_BY' => $request->user()->fullname,
                                        'TIMEMILISECOND' => round(microtime(true) * 1000)
                                    ];

                                    M_CrSurveyDocument::create($data_array_attachment);
                                }
                            }
                        }

                        break;
                }
            }
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $data_prospect = [
                'tujuan_kredit' => $request->order['tujuan_kredit'] ?? null,
                'plafond' => $request->order['plafond'] ?? null,
                'tenor' => $request->order['tenor'] ?? null,
                'category' => $request->order['category'] ?? null,
                'jenis_angsuran' => $request->order['jenis_angsuran'] ?? null,
                'nama' => $request->data_nasabah['nama'] ?? null,
                'tgl_lahir' => date('Y-m-d', strtotime($request->data_nasabah['tgl_lahir'])) ?? null,
                'ktp' => $request->data_nasabah['no_ktp'] ?? null,
                'hp' => $request->data_nasabah['no_hp'] ?? null,
                'kk' => $request->data_nasabah['no_kk'] ?? null,
                'alamat' => $request->data_nasabah['alamat'] ?? null,
                'rt' => $request->data_nasabah['rt'] ?? null,
                'rw' => $request->data_nasabah['rw'] ?? null,
                'province' => $request->data_nasabah['provinsi'] ?? null,
                'city' => $request->data_nasabah['kota'] ?? null,
                'kecamatan' => $request->data_nasabah['kecamatan'] ?? null,
                'kelurahan' => $request->data_nasabah['kelurahan'] ?? null,
                'usaha' => $request->data_survey['usaha'] ?? null,
                'sector' => $request->data_survey['sektor'] ?? null,
                "work_period" => $request->data_survey['lama_bekerja'] ?? null,
                "expenses" => $request->data_survey['pengeluaran'] ?? null,
                "income_personal" => $request->data_survey['pendapatan_pribadi'] ?? null,
                "income_spouse" =>  $request->data_survey['pendapatan_pasangan'] ?? null,
                "income_other" =>  $request->data_survey['pendapatan_lainnya'] ?? null,
                'visit_date' => is_null($request->data_survey['tgl_survey']) ? null : date('Y-m-d', strtotime($request->data_survey['tgl_survey'])),
                'survey_note' => $request->data_survey['catatan_survey'] ?? null,
                'updated_by' => $request->user()->id,
                'updated_at' => $this->timeNow
            ];

            $prospek_check = M_CrSurvey::where('id', $id)->whereNull('deleted_at')->first();

            if (!$prospek_check) {
                throw new Exception("Cr Survey Id Not Found", 404);
            }

            $prospek_check->update($data_prospect);

            compareData(M_CrSurvey::class, $id, $data_prospect, $request);

            if (collect($request->jaminan)->isNotEmpty()) {
                foreach ($request->jaminan as $result) {

                    switch ($result['type']) {
                        case 'kendaraan':

                            $data_array_col = [
                                'TYPE' => $result['atr']['tipe'] ?? null,
                                'BRAND' => $result['atr']['merk'] ?? null,
                                'PRODUCTION_YEAR' => $result['atr']['tahun'] ?? null,
                                'COLOR' => $result['atr']['warna'] ?? null,
                                'ON_BEHALF' => $result['atr']['atas_nama'] ?? null,
                                'POLICE_NUMBER' => $result['atr']['no_polisi'] ?? null,
                                'CHASIS_NUMBER' => $result['atr']['no_rangka'] ?? null,
                                'ENGINE_NUMBER' => $result['atr']['no_mesin'] ?? null,
                                'BPKB_NUMBER' => $result['atr']['no_bpkb'] ?? null,
                                'STNK_NUMBER' => $result['atr']['no_stnk'] ?? null,
                                'STNK_VALID_DATE' => $result['atr']['tgl_stnk'] ?? null,
                                'VALUE' => $result['atr']['nilai'] ?? null,
                                'MOD_DATE' => $this->timeNow,
                                'MOD_BY' => $request->user()->id,
                            ];

                            if (!isset($result['atr']['id'])) {

                                $data_array_col['ID'] = Uuid::uuid7()->toString();
                                $data_array_col['CR_SURVEY_ID'] = $id;
                                $data_array_col['HEADER_ID'] = $result['counter_id'];
                                $data_array_col['CREATE_DATE'] = $this->timeNow;
                                $data_array_col['CREATE_BY'] = $request->user()->id;

                                M_CrGuaranteVehicle::create($data_array_col);
                            } else {

                                $data_array_col['MOD_DATE'] = $this->timeNow;
                                $data_array_col['MOD_BY'] = $request->user()->id;

                                $kendaraan = M_CrGuaranteVehicle::where([
                                    'ID' => $result['atr']['id'],
                                    'HEADER_ID' => $result['counter_id'],
                                    'CR_SURVEY_ID' => $id
                                ])
                                    ->whereNull('DELETED_AT')->first();

                                if (!$kendaraan) {
                                    throw new Exception("Id Jaminan Kendaraan Not Found", 404);
                                }

                                $kendaraan->update($data_array_col);
                            }

                            break;
                        case 'sertifikat':

                            $data_array_col = [
                                'STATUS_JAMINAN' => $result['atr']['status_jaminan'] ?? null,
                                'NO_SERTIFIKAT' => $result['atr']['no_sertifikat'] ?? null,
                                'STATUS_KEPEMILIKAN' => $result['atr']['status_kepemilikan'] ?? null,
                                'IMB' => $result['atr']['imb'] ?? null,
                                'LUAS_TANAH' => $result['atr']['luas_tanah'] ?? null,
                                'LUAS_BANGUNAN' => $result['atr']['luas_bangunan'] ?? null,
                                'LOKASI' => $result['atr']['lokasi'] ?? null,
                                'PROVINSI' => $result['atr']['provinsi'] ?? null,
                                'KAB_KOTA' => $result['atr']['kab_kota'] ?? null,
                                'KECAMATAN' => $result['atr']['kec'] ?? null,
                                'DESA' => $result['atr']['desa'] ?? null,
                                'ATAS_NAMA' => $result['atr']['atas_nama'] ?? null,
                                'NILAI' => $result['atr']['nilai'] ?? null
                            ];

                            if (!isset($result['atr']['id'])) {

                                $data_array_col['ID'] = Uuid::uuid7()->toString();
                                $data_array_col['CR_SURVEY_ID'] = $id;
                                $data_array_col['HEADER_ID'] = $result['counter_id'];
                                $data_array_col['CREATE_DATE'] = $this->timeNow;
                                $data_array_col['CREATE_BY'] = $request->user()->id;

                                M_CrGuaranteSertification::create($data_array_col);
                            } else {

                                $data_array_col['MOD_DATE'] = $this->timeNow;
                                $data_array_col['MOD_BY'] = $request->user()->id;

                                $sertifikasi = M_CrGuaranteSertification::where([
                                    'ID' => $result['atr']['id'],
                                    'HEADER_ID' => $result['counter_id'],
                                    'CR_SURVEY_ID' => $id
                                ])->whereNull('DELETED_AT')->first();

                                if (!$sertifikasi) {
                                    throw new Exception("Id Jaminan Sertifikat Not Found", 404);
                                }

                                $sertifikasi->update($data_array_col);
                            }

                            break;
                    }
                }
            }

            if (collect($request->deleted_kendaraan)->isNotEmpty()) {
                foreach ($request->deleted_kendaraan as $res) {
                    try {
                        $check = M_CrGuaranteVehicle::findOrFail($res['id']);

                        $data = [
                            'DELETED_BY' => $request->user()->id,
                            'DELETED_AT' => $this->timeNow
                        ];

                        $check->update($data);

                        $deleted_docs = M_CrSurveyDocument::where([
                            'CR_SURVEY_ID' => $id,
                            'COUNTER_ID' => $check->HEADER_ID
                        ])->whereIn('TYPE', ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'])->get();

                        if (!$deleted_docs->isEmpty()) {
                            foreach ($deleted_docs as $doc) {
                                $doc->delete();
                            }
                        }
                    } catch (\Exception $e) {
                        DB::rollback();
                        ActivityLogger::logActivity($request, $e->getMessage(), 500);
                        return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
                    }
                }
            }

            if (collect($request->deleted_sertifikat)->isNotEmpty()) {
                foreach ($request->deleted_sertifikat as $res) {
                    try {
                        $check = M_CrGuaranteSertification::findOrFail($res['id']);

                        $data = [
                            'DELETED_BY' => $request->user()->id,
                            'DELETED_AT' => $this->timeNow
                        ];

                        $check->update($data);

                        $deleted_docs = M_CrSurveyDocument::where(['CR_SURVEY_ID' => $id, 'TYPE' => 'sertifikat', 'COUNTER_ID' => $check->HEADER_ID])->get();

                        if (!$deleted_docs->isEmpty()) {
                            foreach ($deleted_docs as $doc) {
                                $doc->delete();
                            }
                        }
                    } catch (\Exception $e) {
                        DB::rollback();
                        ActivityLogger::logActivity($request, $e->getMessage(), 500);
                        return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
                    }
                }
            }

            $data = [
                'CR_SURVEY_ID' => $id
            ];

            $check = M_SurveyApproval::where('CR_SURVEY_ID', $id)->first();

            if (!$request->flag) {
                $data['CODE'] = 'DRSVY';
                $data['APPROVAL_RESULT'] = 'draf survey';

                if ($check) {
                    $check->update($data);
                }
            } else {
                $data['CODE'] = 'WADM';
                $data['APPROVAL_RESULT'] = 'menunggu admin';

                if ($check) {
                    $check->update($data);
                }

                $data_log = [
                    'ID' => $this->uuid,
                    'CODE' => $data['CODE'],
                    'SURVEY_APPROVAL_ID' => $check->ID ? $check->ID : null,
                    'ONCHARGE_APPRVL' => 'AUTO_APPROVED_BY_SYSTEM',
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
                    'APPROVAL_RESULT' => $data['APPROVAL_RESULT']
                ];

                M_SurveyApprovalLog::create($data_log);
            }

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), 'status' => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
        }
    }

    public function destroy(Request $req, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_CrSurvey::findOrFail($id);

            $data = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => $this->timeNow
            ];

            $check->update($data);

            DB::commit();
            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroyImage(Request $req, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_CrSurveyDocument::findOrFail($id);

            $check->delete();

            DB::commit();
            ActivityLogger::logActivity($req, "deleted successfully", 200);
            return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Document Id Not Found', 404);
            return response()->json(['message' => 'Document Id Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function uploadImage(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'cr_prospect_id' => 'required|string'
            ]);

            // Decode the base64 string
            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Survey/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $fileSize = strlen($data);
                $fileSizeInKB = floor($fileSize / 1024);
                // Adjust path

                // Create the URL for the stored image
                $url = URL::to('/') . '/storage/' . 'Cr_Survey/' . $fileName;

                // Prepare data for database insertion
                $data_array_attachment = [
                    'ID' => Uuid::uuid4()->toString(),
                    'CR_SURVEY_ID' => $req->cr_prospect_id,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => isset($req->reff) ? $req->reff : '',
                    'PATH' => $url ?? '',
                    'SIZE' => $fileSizeInKB . ' kb',
                    'CREATED_BY' => $req->user()->fullname,
                    'TIMEMILISECOND' => round(microtime(true) * 1000)
                ];

                // Insert the record into the database
                M_CrSurveyDocument::create($data_array_attachment);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function imageMultiple(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'type' => 'required|string',
                'cr_prospect_id' => 'required|string',
            ]);

            $images = $req->images; // Get the images array from the request
            $uploadedUrls = []; // Array

            foreach ($images as $key => $imageData) { // Use $key to maintain index
                if (preg_match('/^data:image\/(\w+);base64,/', $imageData['image'], $type)) {
                    $data = substr($imageData['image'], strpos($imageData['image'], ',') + 1);
                    $data = base64_decode($data);

                    if ($data === false) {
                        return response()->json(['message' => 'Image data could not be decoded', 'status' => 400], 400);
                    }

                    $extension = strtolower($type[1]);
                    $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                    // Store the image
                    $imagePath = Storage::put("public/Cr_Survey/{$fileName}", $data);
                    $imagePath = str_replace('public/', '', $imagePath);

                    $fileSizeInKB = floor(strlen($data) / 1024);
                    $url = URL::to('/') . '/storage/Cr_Survey/' . $fileName;

                    // Prepare data for database insertion
                    $dataArrayAttachment = [
                        'ID' => Uuid::uuid4()->toString(),
                        'CR_SURVEY_ID' => $req->cr_prospect_id,
                        'TYPE' => $req->type,
                        'COUNTER_ID' => isset($req->reff) ? $req->reff : '',
                        'PATH' => $url,
                        'SIZE' => $fileSizeInKB . ' kb',
                        'CREATED_BY' => $req->user()->fullname,
                        'TIMEMILISECOND' => round(microtime(true) * 1000)
                    ];

                    // Insert the record into the database
                    M_CrSurveyDocument::create($dataArrayAttachment);

                    // Store the uploaded image URL with a key number
                    DB::commit();
                    $uploadedUrls["url_{$key}"] = $url; // Use the loop index as the key
                } else {
                    return response()->json(['message' => 'No valid image file provided', 'status' => 400], 400);
                }
            }

            return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $uploadedUrls], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function checkOrder(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = [];
            $ktp = $request->ktp;
            $kk = $request->kk;

            $checkIdNumber = DB::table('credit as a')
                ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->where('a.STATUS', 'A')
                ->where('b.ID_NUMBER', $ktp)
                ->count();

            if ($checkIdNumber > 1) {
                $data[] = [
                    "ktp" => false,
                    "message" => "No KTP {$ktp} Masih Ada yang Aktif"
                ];
            } else {
                $data[] = [
                    "ktp" => true
                ];
            }

            $checkKkNumber = DB::table('credit as a')
                ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->where('a.STATUS', 'A')
                ->where('b.KK_NUMBER', $kk)
                ->count();

            if ($checkKkNumber == 2) {
                $data[] = [
                    "kk" => false,
                    "message" => "No KK {$kk} Aktif Lebih Dari 2"
                ];
            } else {
                $data[] = [
                    "kk" => true
                ];
            }

            return response()->json($data, 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

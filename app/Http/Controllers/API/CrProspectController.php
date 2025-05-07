<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Survey\SurveyRepository;
use App\Http\Resources\R_CrProspect;
use App\Http\Resources\R_CrSurvey;
use App\Http\Resources\R_CrSurveyDetail;
use App\Models\M_CrGuaranteSertification;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrProspect;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
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

class CrProspectController extends Controller
{
    private $uuid;
    private $timeNow;
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->uuid = Uuid::uuid7()->toString();
        $this->timeNow = Carbon::now();
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getListSurveyByMcf = $this->SurveyRepository->getListSurveyByMcf($request);

            $dto = R_CrSurvey::collection($getListSurveyByMcf);

            return response()->json(['response' => $dto], 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $checkSurveyExist = $this->CrSurvey
                // ->with(['cr_guarante_vehicle', 'cr_guarante_sertification', 'survey_approval'])
                ->where('id', $id)
                ->first();

            if (!$checkSurveyExist) {
                throw new Exception("Prospect Id Is Not Exist", 409);
            }

            $dto = new R_CrProspect($checkSurveyExist);

            return response()->json(['response' => $dto], 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'id' => 'required|string|unique:cr_prospect',
            ]);

            $this->insertCrProspect($request);

            // if (collect($request->jaminan)->isNotEmpty()) {
            //     $this->insert_guarante($request);
            // }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    private function insertCrProspect($request)
    {
        $getUserId = $request->user()->id;

        $data_array = [
            'id' => $request->id,
            'ao_id' => $getUserId,
            'visit_date' => $request->tgl_prospect ?? null,
            'tujuan_kredit' => $request->tujuan_kredit ?? '',
            'jenis_produk' => $request->jenis_produk ?? '',
            'plafond' => $request->plafond ?? '',
            'tenor' => $request->tenor ?? '',
            'nama' => $request->nama ?? '',
            'ktp' => $request->ktp ?? '',
            'kk' => $request->kk ?? '',
            'tgl_lahir' => $request->tgl_lahir ?? null,
            'alamat' => $request->alamat ?? '',
            'rt' => $request->rt ?? '',
            'rw' => $request->rw ?? '',
            'province' => $request->provinsi ?? '',
            'city' => $request->kota ?? '',
            'kelurahan' => $request->kelurahan ?? '',
            'kecamatan' => $request->kecamatan ?? '',
            'zip_code' => $request->kode_pos ?? '',
            'hp' => $request->hp ?? '',
            'usaha' => $request->usaha ?? '',
            'sector' => $request->sektor ?? '',
            'coordinate' => $request->kordinat ?? '',
            'accurate' => $request->accurate ?? '',
            'slik' => $request->slik_flag ?? '',
        ];

        M_CrProspect::create($data_array);
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
                            'HEADER_ID' => $result['counter_id'] ?? null,
                            'POSITION_FLAG' => $result['atr']['kondisi_jaminan'] ?? null,
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

    // private function createCrProspekApproval($request)
    // {
    //     $data = [
    //         'CR_SURVEY_ID' => $request->id
    //     ];

    //     if (!$request->flag) {
    //         $data['CODE'] = 'DRSVY';
    //         $data['APPROVAL_RESULT'] = 'draf survey';
    //     } else {
    //         $data['CODE'] = 'WADM';
    //         $data['APPROVAL_RESULT'] = 'menunggu admin';
    //     }

    //     $approval = M_SurveyApproval::create($data);

    //     $data_log = [
    //         'ID' => $this->uuid,
    //         'CODE' => $data['CODE'],
    //         'SURVEY_APPROVAL_ID' => $approval->ID,
    //         'ONCHARGE_APPRVL' => 'AUTO_APPROVED_BY_SYSTEM',
    //         'ONCHARGE_PERSON' => $request->user()->id,
    //         'ONCHARGE_TIME' => Carbon::now(),
    //         'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
    //         'APPROVAL_RESULT' => $data['APPROVAL_RESULT']
    //     ];

    //     M_SurveyApprovalLog::create($data_log);
    // }

    // public function update(Request $request, $id)
    // {
    //     DB::beginTransaction();
    //     try {

    //         $data_prospect = [
    //             'tujuan_kredit' => $request->order['tujuan_kredit'] ?? null,
    //             'plafond' => $request->order['plafond'] ?? null,
    //             'tenor' => $request->order['tenor'] ?? null,
    //             'category' => $request->order['category'] ?? null,
    //             'jenis_angsuran' => $request->order['jenis_angsuran'] ?? null,
    //             'nama' => $request->data_nasabah['nama'] ?? null,
    //             'tgl_lahir' => date('Y-m-d', strtotime($request->data_nasabah['tgl_lahir'])) ?? null,
    //             'ktp' => $request->data_nasabah['no_ktp'] ?? null,
    //             'hp' => $request->data_nasabah['no_hp'] ?? null,
    //             'kk' => $request->data_nasabah['no_kk'] ?? null,
    //             'alamat' => $request->data_nasabah['alamat'] ?? null,
    //             'rt' => $request->data_nasabah['rt'] ?? null,
    //             'rw' => $request->data_nasabah['rw'] ?? null,
    //             'province' => $request->data_nasabah['provinsi'] ?? null,
    //             'city' => $request->data_nasabah['kota'] ?? null,
    //             'kecamatan' => $request->data_nasabah['kecamatan'] ?? null,
    //             'kelurahan' => $request->data_nasabah['kelurahan'] ?? null,
    //             'usaha' => $request->data_survey['usaha'] ?? null,
    //             'sector' => $request->data_survey['sektor'] ?? null,
    //             "work_period" => $request->data_survey['lama_bekerja'] ?? null,
    //             "expenses" => $request->data_survey['pengeluaran'] ?? null,
    //             "income_personal" => $request->data_survey['pendapatan_pribadi'] ?? null,
    //             "income_spouse" =>  $request->data_survey['pendapatan_pasangan'] ?? null,
    //             "income_other" =>  $request->data_survey['pendapatan_lainnya'] ?? null,
    //             'visit_date' => is_null($request->data_survey['tgl_survey']) ? null : date('Y-m-d', strtotime($request->data_survey['tgl_survey'])),
    //             'survey_note' => $request->data_survey['catatan_survey'] ?? null,
    //             'updated_by' => $request->user()->id,
    //             'updated_at' => $this->timeNow
    //         ];

    //         $prospek_check = M_CrSurvey::where('id', $id)->whereNull('deleted_at')->first();

    //         if (!$prospek_check) {
    //             throw new Exception("Cr Survey Id Not Found", 404);
    //         }

    //         $prospek_check->update($data_prospect);

    //         compareData(M_CrSurvey::class, $id, $data_prospect, $request);

    //         if (collect($request->jaminan)->isNotEmpty()) {
    //             foreach ($request->jaminan as $result) {

    //                 switch ($result['type']) {
    //                     case 'kendaraan':

    //                         $data_array_col = [
    //                             'POSITION_FLAG' => $result['atr']['kondisi_jaminan'] ?? null,
    //                             'TYPE' => $result['atr']['tipe'] ?? null,
    //                             'BRAND' => $result['atr']['merk'] ?? null,
    //                             'PRODUCTION_YEAR' => $result['atr']['tahun'] ?? null,
    //                             'COLOR' => $result['atr']['warna'] ?? null,
    //                             'ON_BEHALF' => $result['atr']['atas_nama'] ?? null,
    //                             'POLICE_NUMBER' => $result['atr']['no_polisi'] ?? null,
    //                             'CHASIS_NUMBER' => $result['atr']['no_rangka'] ?? null,
    //                             'ENGINE_NUMBER' => $result['atr']['no_mesin'] ?? null,
    //                             'BPKB_NUMBER' => $result['atr']['no_bpkb'] ?? null,
    //                             'STNK_NUMBER' => $result['atr']['no_stnk'] ?? null,
    //                             'STNK_VALID_DATE' => $result['atr']['tgl_stnk'] ?? null,
    //                             'VALUE' => $result['atr']['nilai'] ?? null,
    //                             'MOD_DATE' => $this->timeNow,
    //                             'MOD_BY' => $request->user()->id,
    //                         ];

    //                         if (!isset($result['atr']['id'])) {

    //                             $data_array_col['ID'] = Uuid::uuid7()->toString();
    //                             $data_array_col['CR_SURVEY_ID'] = $id;
    //                             $data_array_col['HEADER_ID'] = $result['counter_id'];
    //                             $data_array_col['CREATE_DATE'] = $this->timeNow;
    //                             $data_array_col['CREATE_BY'] = $request->user()->id;

    //                             M_CrGuaranteVehicle::create($data_array_col);
    //                         } else {

    //                             $data_array_col['MOD_DATE'] = $this->timeNow;
    //                             $data_array_col['MOD_BY'] = $request->user()->id;

    //                             $kendaraan = M_CrGuaranteVehicle::where([
    //                                 'ID' => $result['atr']['id'],
    //                                 'HEADER_ID' => $result['counter_id'],
    //                                 'CR_SURVEY_ID' => $id
    //                             ])
    //                                 ->whereNull('DELETED_AT')->first();

    //                             if (!$kendaraan) {
    //                                 throw new Exception("Id Jaminan Kendaraan Not Found", 404);
    //                             }

    //                             $kendaraan->update($data_array_col);
    //                         }

    //                         break;
    //                     case 'sertifikat':

    //                         $data_array_col = [
    //                             'STATUS_JAMINAN' => $result['atr']['status_jaminan'] ?? null,
    //                             'NO_SERTIFIKAT' => $result['atr']['no_sertifikat'] ?? null,
    //                             'STATUS_KEPEMILIKAN' => $result['atr']['status_kepemilikan'] ?? null,
    //                             'IMB' => $result['atr']['imb'] ?? null,
    //                             'LUAS_TANAH' => $result['atr']['luas_tanah'] ?? null,
    //                             'LUAS_BANGUNAN' => $result['atr']['luas_bangunan'] ?? null,
    //                             'LOKASI' => $result['atr']['lokasi'] ?? null,
    //                             'PROVINSI' => $result['atr']['provinsi'] ?? null,
    //                             'KAB_KOTA' => $result['atr']['kab_kota'] ?? null,
    //                             'KECAMATAN' => $result['atr']['kec'] ?? null,
    //                             'DESA' => $result['atr']['desa'] ?? null,
    //                             'ATAS_NAMA' => $result['atr']['atas_nama'] ?? null,
    //                             'NILAI' => $result['atr']['nilai'] ?? null
    //                         ];

    //                         if (!isset($result['atr']['id'])) {

    //                             $data_array_col['ID'] = Uuid::uuid7()->toString();
    //                             $data_array_col['CR_SURVEY_ID'] = $id;
    //                             $data_array_col['HEADER_ID'] = $result['counter_id'];
    //                             $data_array_col['CREATE_DATE'] = $this->timeNow;
    //                             $data_array_col['CREATE_BY'] = $request->user()->id;

    //                             M_CrGuaranteSertification::create($data_array_col);
    //                         } else {

    //                             $data_array_col['MOD_DATE'] = $this->timeNow;
    //                             $data_array_col['MOD_BY'] = $request->user()->id;

    //                             $sertifikasi = M_CrGuaranteSertification::where([
    //                                 'ID' => $result['atr']['id'],
    //                                 'HEADER_ID' => $result['counter_id'],
    //                                 'CR_SURVEY_ID' => $id
    //                             ])->whereNull('DELETED_AT')->first();

    //                             if (!$sertifikasi) {
    //                                 throw new Exception("Id Jaminan Sertifikat Not Found", 404);
    //                             }

    //                             $sertifikasi->update($data_array_col);
    //                         }

    //                         break;
    //                 }
    //             }
    //         }

    //         if (collect($request->deleted_kendaraan)->isNotEmpty()) {
    //             foreach ($request->deleted_kendaraan as $res) {
    //                 try {
    //                     $check = M_CrGuaranteVehicle::findOrFail($res['id']);

    //                     $data = [
    //                         'DELETED_BY' => $request->user()->id,
    //                         'DELETED_AT' => $this->timeNow
    //                     ];

    //                     $check->update($data);

    //                     $deleted_docs = M_CrSurveyDocument::where([
    //                         'CR_SURVEY_ID' => $id,
    //                         'COUNTER_ID' => $check->HEADER_ID
    //                     ])->whereIn('TYPE', ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'])->get();

    //                     if (!$deleted_docs->isEmpty()) {
    //                         foreach ($deleted_docs as $doc) {
    //                             $doc->delete();
    //                         }
    //                     }
    //                 } catch (\Exception $e) {
    //                     DB::rollback();
    //                     ActivityLogger::logActivity($request, $e->getMessage(), 500);
    //                     return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //                 }
    //             }
    //         }

    //         if (collect($request->deleted_sertifikat)->isNotEmpty()) {
    //             foreach ($request->deleted_sertifikat as $res) {
    //                 try {
    //                     $check = M_CrGuaranteSertification::findOrFail($res['id']);

    //                     $data = [
    //                         'DELETED_BY' => $request->user()->id,
    //                         'DELETED_AT' => $this->timeNow
    //                     ];

    //                     $check->update($data);

    //                     $deleted_docs = M_CrSurveyDocument::where(['CR_SURVEY_ID' => $id, 'TYPE' => 'sertifikat', 'COUNTER_ID' => $check->HEADER_ID])->get();

    //                     if (!$deleted_docs->isEmpty()) {
    //                         foreach ($deleted_docs as $doc) {
    //                             $doc->delete();
    //                         }
    //                     }
    //                 } catch (\Exception $e) {
    //                     DB::rollback();
    //                     ActivityLogger::logActivity($request, $e->getMessage(), 500);
    //                     return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //                 }
    //             }
    //         }

    //         $data = [
    //             'CR_SURVEY_ID' => $id
    //         ];

    //         $check = M_SurveyApproval::where('CR_SURVEY_ID', $id)->first();

    //         if (!$request->flag) {
    //             $data['CODE'] = 'DRSVY';
    //             $data['APPROVAL_RESULT'] = 'draf survey';

    //             if ($check) {
    //                 $check->update($data);
    //             }
    //         } else {
    //             $data['CODE'] = 'WADM';
    //             $data['APPROVAL_RESULT'] = 'menunggu admin';

    //             if ($check) {
    //                 $check->update($data);
    //             }

    //             $data_log = [
    //                 'ID' => $this->uuid,
    //                 'CODE' => $data['CODE'],
    //                 'SURVEY_APPROVAL_ID' => $check->ID ? $check->ID : null,
    //                 'ONCHARGE_APPRVL' => 'AUTO_APPROVED_BY_SYSTEM',
    //                 'ONCHARGE_PERSON' => $request->user()->id,
    //                 'ONCHARGE_TIME' => Carbon::now(),
    //                 'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
    //                 'APPROVAL_RESULT' => $data['APPROVAL_RESULT']
    //             ];

    //             M_SurveyApprovalLog::create($data_log);
    //         }

    //         DB::commit();
    //         ActivityLogger::logActivity($request, "Success", 200);
    //         return response()->json(['message' => 'updated successfully'], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request, 'Cr Prospect Id Not Found', 404);
    //         return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), 'status' => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
    //     }
    // }

    // public function destroy(Request $req, $id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $check = M_CrSurvey::findOrFail($id);

    //         $data = [
    //             'deleted_by' => $req->user()->id,
    //             'deleted_at' => $this->timeNow
    //         ];

    //         $check->update($data);

    //         DB::commit();
    //         ActivityLogger::logActivity($req, "Success", 200);
    //         return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
    //         return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    // public function destroyImage(Request $req, $id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $check = M_CrSurveyDocument::findOrFail($id);

    //         $check->delete();

    //         DB::commit();
    //         ActivityLogger::logActivity($req, "deleted successfully", 200);
    //         return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, 'Document Id Not Found', 404);
    //         return response()->json(['message' => 'Document Id Not Found', "status" => 404], 404);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    // public function uploadImage(Request $req)
    // {
    //     DB::beginTransaction();
    //     try {

    //         $this->validate($req, [
    //             'image' => 'required|string',
    //             'type' => 'required|string',
    //             'cr_prospect_id' => 'required|string'
    //         ]);

    //         // Decode the base64 string
    //         if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
    //             $data = substr($req->image, strpos($req->image, ',') + 1);
    //             $data = base64_decode($data);

    //             // Generate a unique filename
    //             $extension = strtolower($type[1]); // Get the image extension
    //             $fileName = Uuid::uuid4()->toString() . '.' . $extension;

    //             // Store the image
    //             $image_path = Storage::put("public/Cr_Survey/{$fileName}", $data);
    //             $image_path = str_replace('public/', '', $image_path);

    //             $fileSize = strlen($data);
    //             $fileSizeInKB = floor($fileSize / 1024);
    //             // Adjust path

    //             // Create the URL for the stored image
    //             $url = URL::to('/') . '/storage/' . 'Cr_Survey/' . $fileName;

    //             // Prepare data for database insertion
    //             $data_array_attachment = [
    //                 'ID' => Uuid::uuid4()->toString(),
    //                 'CR_SURVEY_ID' => $req->cr_prospect_id,
    //                 'TYPE' => $req->type,
    //                 'COUNTER_ID' => isset($req->reff) ? $req->reff : '',
    //                 'PATH' => $url ?? '',
    //                 'SIZE' => $fileSizeInKB . ' kb',
    //                 'CREATED_BY' => $req->user()->fullname,
    //                 'TIMEMILISECOND' => round(microtime(true) * 1000)
    //             ];

    //             // Insert the record into the database
    //             M_CrSurveyDocument::create($data_array_attachment);

    //             DB::commit();
    //             return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
    //         } else {
    //             DB::rollback();
    //             ActivityLogger::logActivity($req, 'No image file provided', 400);
    //             return response()->json(['message' => 'No image file provided', "status" => 400], 400);
    //         }
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    // public function imageMultiple(Request $req)
    // {
    //     DB::beginTransaction();
    //     try {

    //         $this->validate($req, [
    //             'type' => 'required|string',
    //             'cr_prospect_id' => 'required|string',
    //         ]);

    //         $images = $req->images; // Get the images array from the request
    //         $uploadedUrls = []; // Array

    //         foreach ($images as $key => $imageData) { // Use $key to maintain index
    //             if (preg_match('/^data:image\/(\w+);base64,/', $imageData['image'], $type)) {
    //                 $data = substr($imageData['image'], strpos($imageData['image'], ',') + 1);
    //                 $data = base64_decode($data);

    //                 if ($data === false) {
    //                     return response()->json(['message' => 'Image data could not be decoded', 'status' => 400], 400);
    //                 }

    //                 $extension = strtolower($type[1]);
    //                 $fileName = Uuid::uuid4()->toString() . '.' . $extension;

    //                 // Store the image
    //                 $imagePath = Storage::put("public/Cr_Survey/{$fileName}", $data);
    //                 $imagePath = str_replace('public/', '', $imagePath);

    //                 $fileSizeInKB = floor(strlen($data) / 1024);
    //                 $url = URL::to('/') . '/storage/Cr_Survey/' . $fileName;

    //                 // Prepare data for database insertion
    //                 $dataArrayAttachment = [
    //                     'ID' => Uuid::uuid4()->toString(),
    //                     'CR_SURVEY_ID' => $req->cr_prospect_id,
    //                     'TYPE' => $req->type,
    //                     'COUNTER_ID' => isset($req->reff) ? $req->reff : '',
    //                     'PATH' => $url,
    //                     'SIZE' => $fileSizeInKB . ' kb',
    //                     'CREATED_BY' => $req->user()->fullname,
    //                     'TIMEMILISECOND' => round(microtime(true) * 1000)
    //                 ];

    //                 // Insert the record into the database
    //                 M_CrSurveyDocument::create($dataArrayAttachment);

    //                 // Store the uploaded image URL with a key number
    //                 DB::commit();
    //                 $uploadedUrls["url_{$key}"] = $url; // Use the loop index as the key
    //             } else {
    //                 return response()->json(['message' => 'No valid image file provided', 'status' => 400], 400);
    //             }
    //         }

    //         return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $uploadedUrls], 200);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }
}

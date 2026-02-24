<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Service\OrderValidationService;
use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Enum\UserPosition\UserPositionEnum;
use App\Http\Controllers\Validation\Validation;
use App\Http\Resources\R_CrApplicationDetail;
use App\Http\Resources\R_CrProspect;
use App\Http\Resources\R_DetailDocument;
use App\Models\M_ApplicationApproval;
use App\Models\M_ApplicationApprovalLog;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationBank;
use App\Models\M_CrApplicationGuarantor;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_Credit;
use App\Models\M_CrGuaranteSertification;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_CustomerExtra;
use App\Models\M_SurveyApproval;
use App\Models\M_SurveyApprovalLog;
use App\Models\M_Taksasi;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CrAppilcationController extends Controller
{
    protected $adminfee;
    protected $log;
    protected $validation;
    protected $validationService;

    public function __construct(
        AdminFeeController $admin_fee,
        ExceptionHandling $log,
        Validation $validation,
        OrderValidationService $validationService
    ) {
        $this->adminfee = $admin_fee;
        $this->log = $log;
        $this->validation = $validation;
        $this->validationService = $validationService;
    }

    public function index(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request);
            return response()->json(['response' => $data], 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    // public function showAdmins(Request $request)
    // {
    //     try {
    //         $user = $request->user();
    //         $branchId = $user->branch_id;
    //         $isHO = strtoupper($user->position) === UserPositionEnum::HO;

    //         $no_order = $request->query('no_order');
    //         $nama = $request->query('nama');
    //         $tgl_order = $request->query('tgl_order');

    //         $data = M_CrSurvey::select(
    //             'cr_survey.id as id',
    //             'cr_survey.jenis_angsuran',
    //             'cr_application.INSTALLMENT_TYPE',
    //             'cr_application.ORDER_NUMBER as order_number',
    //             'cr_survey.visit_date',
    //             DB::raw("COALESCE(cr_personal.NAME, cr_survey.nama) as nama_debitur"),
    //             'cr_survey.alamat',
    //             'cr_survey.hp',
    //             'survey_approval.CODE',
    //             'survey_approval.APPROVAL_RESULT',
    //             'survey_approval.ONCHARGE_TIME',
    //             DB::raw("COALESCE(cr_application.SUBMISSION_VALUE, cr_survey.plafond) as plafond")
    //         )
    //             ->leftJoin('survey_approval', 'survey_approval.CR_SURVEY_ID', '=', 'cr_survey.id')
    //             ->leftJoin('cr_application', 'cr_application.CR_SURVEY_ID', '=', 'cr_survey.id')
    //             ->leftJoin('cr_personal', 'cr_personal.APPLICATION_ID', '=', 'cr_application.ID')
    //             ->where('survey_approval.CODE', '!=', 'DRSVY')
    //             ->whereNull('cr_survey.deleted_at')
    //             ->orderBy('cr_survey.created_at', 'desc');

    //         if (!$isHO) {
    //             $data->where('cr_survey.branch_id', $branchId);
    //         }

    //         if (!empty($no_order)) {
    //             $data->where('cr_application.ORDER_NUMBER', 'like', '%' . $no_order . '%');
    //         }

    //         if (!empty($nama)) {
    //             $data->where(DB::raw("COALESCE(cr_personal.NAME, cr_survey.nama)"), 'like', '%' . $nama . '%');
    //         }

    //         if (!empty($tgl_order)) {
    //             $data->whereRaw("DATE_FORMAT(cr_survey.visit_date, '%Y-%m-%d') = ?", [$tgl_order]);
    //         }

    //         if (empty($no_order) && empty($nama) && empty($tgl_order)) {
    //             $data->whereRaw("DATE_FORMAT(cr_survey.visit_date, '%Y%m%d') = ?", [Carbon::now()->format('Ymd')]);
    //         }

    //         $results = $data->get();

    //         $dto = R_CrProspect::collection($results);

    //         return response()->json(['message' => true, "status" => 200, 'response' => $dto], 200);
    //     } catch (Exception $e) {
    //         return $this->log->logError($e, $request);
    //     }
    // }

    public function showAdmins(Request $request)
    {
        try {
            $user = $request->user();
            $branchId = $user->branch_id;
            $isHO = strtoupper($user->position) === UserPositionEnum::HO;

            $no_order = $request->query('no_order');
            $nama = $request->query('nama');
            $tgl_order = $request->query('tgl_order');

            $query = M_CrSurvey::query()
                ->with([
                    'cr_application:id,CR_SURVEY_ID,INSTALLMENT_TYPE,ORDER_NUMBER,SUBMISSION_VALUE',
                    'cr_application.cr_personal:id,APPLICATION_ID,NAME',
                    'survey_approval:id,CR_SURVEY_ID,CODE,APPROVAL_RESULT,ONCHARGE_TIME'
                ])
                ->whereHas('survey_approval', function ($q) {
                    $q->where('CODE', '!=', 'DRSVY');
                })
                ->whereNull('deleted_at');


            // ✅ Filter HO
            if (!$isHO) {
                $query->where('branch_id', $branchId);
            }

            // ✅ Filter no_order
            if (!empty($no_order)) {
                $query->whereHas('cr_application', function ($q) use ($no_order) {
                    $q->where('ORDER_NUMBER', 'like', "%{$no_order}%");
                });
            }

            // ✅ Filter nama
            if (!empty($nama)) {
                $query->where(function ($q) use ($nama) {
                    $q->where('nama', 'like', "%{$nama}%")
                        ->orWhereHas('cr_application.cr_personal', function ($q2) use ($nama) {
                            $q2->where('NAME', 'like', "%{$nama}%");
                        });
                });
            }

            // ✅ Filter tanggal
            if (!empty($tgl_order)) {
                $query->whereDate('visit_date', $tgl_order);
            }

            // ✅ Default hari ini
            if (empty($no_order) && empty($nama) && empty($tgl_order)) {
                $query->whereDate('visit_date', now()->toDateString());
            }

            $results = $query
                ->latest('created_at')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'jenis_angsuran' => $item->jenis_angsuran,
                        'installment_type' => optional($item->cr_application)->INSTALLMENT_TYPE,
                        'order_number' => optional($item->cr_application)->ORDER_NUMBER,
                        'visit_date' => $item->visit_date,
                        'nama_debitur' => optional($item->cr_application?->cr_personal)->NAME
                            ?? $item->nama,
                        'alamat' => $item->alamat,
                        'hp' => $item->hp,
                        'code' => optional($item->survey_approval)->CODE,
                        'approval_result' => optional($item->survey_approval)->APPROVAL_RESULT,
                        'oncharge_time' => optional($item->survey_approval)->ONCHARGE_TIME,
                        'plafond' => optional($item->cr_application)->SUBMISSION_VALUE
                            ?? $item->plafond,
                    ];
                });

            $dto = R_CrProspect::collection($results);

            return response()->json(['message' => true, "status" => 200, 'response' => $dto], 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function showKapos(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request, 'WAKPS', 'WAHO', 'APKPS', 'APHO', 'REORHO', 'CLHO', 'REORKPS', 'CLKPS');
            return response()->json(['message' => true, "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function showHo(Request $request)
    {
        try {
            $data = M_CrApplication::fpkListData($request, 'APKPS', 'WAKPS', 'WAHO', 'APHO', 'REORHO', 'CLHO', 'REORKPS', 'CLKPS');
            return response()->json(['message' => true, "status" => 200, 'response' => $data], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $check = M_CrApplication::where('CR_SURVEY_ID', $id)->whereNull('deleted_at')->first();

            if (!$check) {
                $check_application_id = M_CrApplication::where('ID', $id)->whereNull('deleted_at')->first();
            } else {
                $check_application_id = $check;
            }

            $surveyID = $check_application_id->CR_SURVEY_ID;

            if (!isset($surveyID)  || $surveyID == '') {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $detail_prospect = M_CrSurvey::where('id', $surveyID)->first();

            // $getSurvey = M_CrSurvey::with([
            //     'cr_application',
            //     'cr_application.cr_personal',
            //     'cr_application.cr_personal_extra',
            //     'cr_application.cr_oder',
            //     'cr_application.cr_guarantor',
            //     'cr_application.cr_spouse',
            //     'cr_application.approval',
            //     'cr_application.credit',
            //     'cr_guarante_vehicle',
            //     'cr_guarante_sertification',
            // ])->where('id', $id)->first();

            // if (!$getSurvey) {
            //     throw new Exception("Id FPK Is Not Exist", 404);
            // }

            // $dto = new R_CrApplicationDetail($getSurvey);

            // return response()->json($dto, 200);
            // die;

            return response()->json(['response' =>  $this->resourceDetail($detail_prospect, $check_application_id)], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $uuid = Uuid::uuid4()->toString();

            $check_prospect_id = M_CrSurvey::where('id', $request->data_order['cr_prospect_id'])
                ->whereNull('deleted_at')->first();

            if (!$check_prospect_id) {
                throw new Exception("Id Kunjungan Is Not Exist", 404);
            }

            // self::insert_cr_application($request,$uuid);
            // // self::update_cr_prospect($request,$check_prospect_id);
            // self::insert_cr_personal($request,$uuid);
            // self::insert_cr_personal_extra($request,$uuid);
            $this->insert_bank_account($request, $uuid);

            DB::commit();
            return response()->json(['message' => 'Application created successfully', "status" => 200], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'flag_pengajuan' => 'required|string',
            ]);

            $check_application_id = M_CrApplication::find($id);

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $surveyID = $check_application_id->CR_SURVEY_ID;
            $timenow = Carbon::now('Asia/Jakarta');

            $this->insert_cr_application($request, $check_application_id);
            $this->insert_cr_personal($request, $id);
            $this->insert_cr_order($request, $surveyID, $id);
            $this->insert_cr_personal_extra($request, $id);

            if (!empty($request->penjamin)) {
                $this->insert_cr_guarantor($request, $id);
            }

            if (!empty($request->pasangan)) {
                $this->insert_cr_spouse($request, $id);
            }

            $this->insert_bank_account($request, $id);
            $this->insert_taksasi($request, $surveyID);
            $this->insert_application_approval($request, $id, $surveyID, $request->flag_pengajuan);

            if (collect($request->jaminan)->isNotEmpty()) {
                foreach ($request->jaminan as $result) {

                    switch (strtolower($result['type'])) {
                        case 'kendaraan':

                            $getBrand = $result['atr']['merk'];
                            $getTipe = $result['atr']['tipe'];

                            $getVehicleType = M_Taksasi::where('brand', $getBrand)
                                ->whereRaw("CONCAT(code,' - ', descr) = ?", $getTipe)
                                ->first();

                            $data_array_col = [
                                'POSITION_FLAG' => $result['atr']['kondisi_jaminan'] ?? null,
                                'VEHICLE_TYPE' => $getVehicleType->vehicle_type ?? '',
                                'TYPE' => $getTipe ?? '',
                                'BRAND' => $getBrand ?? '',
                                'PRODUCTION_YEAR' => $result['atr']['tahun'] ?? null,
                                'COLOR' => $result['atr']['warna'] ?? null,
                                'ON_BEHALF' => $result['atr']['atas_nama'] ?? null,
                                'POLICE_NUMBER' => $result['atr']['no_polisi'] ?? null,
                                'CHASIS_NUMBER' => $result['atr']['no_rangka'] ?? null,
                                'ENGINE_NUMBER' => $result['atr']['no_mesin'] ?? null,
                                'BPKB_NUMBER' => $result['atr']['no_bpkb'] ?? null,
                                'BPKB_ADDRESS' => $result['atr']['alamat_bpkb'] ?? null,
                                'INVOICE_NUMBER' => $result['atr']['no_faktur'] ?? null,
                                'STNK_NUMBER' => $result['atr']['no_stnk'] ?? null,
                                'STNK_VALID_DATE' => $result['atr']['tgl_stnk'] ?? null,
                                'VALUE' => $result['atr']['nilai'] ?? null
                            ];

                            if (!isset($result['atr']['id'])) {

                                $data_array_col['ID'] = Uuid::uuid7()->toString();
                                $data_array_col['CR_SURVEY_ID'] = $surveyID;
                                $data_array_col['HEADER_ID'] = $result['counter_id'];
                                $data_array_col['CREATE_DATE'] = $timenow;
                                $data_array_col['CREATE_BY'] = $request->user()->id;

                                M_CrGuaranteVehicle::create($data_array_col);
                            } else {

                                $data_array_col['MOD_DATE'] = $timenow;
                                $data_array_col['MOD_BY'] = $request->user()->id;

                                $kendaraan = M_CrGuaranteVehicle::where([
                                    'ID' => $result['atr']['id'],
                                    'HEADER_ID' => $result['counter_id'],
                                    'CR_SURVEY_ID' => $surveyID
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
                                $data_array_col['CR_SURVEY_ID'] = $surveyID;
                                $data_array_col['HEADER_ID'] = $result['counter_id'];
                                $data_array_col['CREATE_DATE'] = $timenow;
                                $data_array_col['CREATE_BY'] = $request->user()->id;

                                M_CrGuaranteSertification::create($data_array_col);
                            } else {

                                $data_array_col['MOD_DATE'] = $timenow;
                                $data_array_col['MOD_BY'] = $request->user()->id;

                                $sertifikasi = M_CrGuaranteSertification::where([
                                    'ID' => $result['atr']['id'],
                                    'HEADER_ID' => $result['counter_id'],
                                    'CR_SURVEY_ID' => $surveyID
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
                            'DELETED_AT' => $timenow
                        ];

                        $check->update($data);

                        $deleted_docs = M_CrSurveyDocument::where([
                            'CR_SURVEY_ID' => $surveyID,
                            'COUNTER_ID' => $check->HEADER_ID
                        ])->whereIn('TYPE', ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'])->get();

                        if (!$deleted_docs->isEmpty()) {
                            foreach ($deleted_docs as $doc) {
                                $doc->delete();
                            }
                        }
                    } catch (\Exception $e) {
                        DB::rollback();

                        return $this->log->logError($e, $request);
                    }
                }
            }

            if (collect($request->deleted_sertifikat)->isNotEmpty()) {
                foreach ($request->deleted_sertifikat as $res) {
                    try {
                        $check = M_CrGuaranteSertification::findOrFail($res['id']);

                        $data = [
                            'DELETED_BY' => $request->user()->id,
                            'DELETED_AT' => $timenow
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

                        return $this->log->logError($e, $request);
                    }
                }
            }

            if (collect($request->deleted_penjamin)->isNotEmpty()) {
                foreach ($request->deleted_penjamin as $res) {
                    $check = M_CrApplicationGuarantor::where('ID', $res['id'])->get();

                    if (!$check->isEmpty()) {
                        foreach ($check as $doc) {
                            $doc->delete();
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Updated Successfully', "status" => 200], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    private function insert_cr_application($request, $applicationModel)
    {
        $ekstra = $request->ekstra;
        $user = $request->user();

        $creditType = $ekstra['jenis_angsuran'] ?? null;
        $tenor = $ekstra['tenor'] ?? null;
        $submissionValue = $ekstra['nilai_yang_diterima'] ?? null;

        // Default field values
        $fields = [
            'FORM_NUMBER'       => '',
            'CUST_CODE'         => '',
            'ENTRY_DATE'        => now()->format('Y-m-d'),
            'SUBMISSION_VALUE'  => $submissionValue,
            'CREDIT_TYPE'       => $creditType,
            'INSTALLMENT_COUNT' => null,
            'PERIOD'            => $ekstra['periode'] ?? null,
            'INSTALLMENT_TYPE'  => $creditType,
            'TENOR'             => $tenor,
            'OPT_PERIODE'       => $ekstra['opt_periode'] ?? null,
            'VERSION'           => 1,
        ];

        // Add creator fields if new record
        if (!$applicationModel) {
            $fields = array_merge($fields, [
                'ID'           => Uuid::uuid7()->toString(),
                'CREATE_DATE'  => now()->format('Y-m-d'),
                'CREATE_BY'    => $user->id,
                'BRANCH'       => $user->branch_id,
                'ORDER_NUMBER' => $this->createAutoCode(M_CrApplication::class, 'ORDER_NUMBER', 'COR'),
            ]);

            M_CrApplication::create($fields);
            return;
        }

        // Calculate admin fee data (recreate request object to pass to fee())
        $feeRequest = new Request([
            'plafond'         => $submissionValue,
            'jenis_angsuran'  => $creditType,
            'tenor'           => $tenor,
        ]);

        $feeData = $this->adminfee->fee($feeRequest)->getData(true);

        // Assign fee-related fields
        $fields = array_merge($fields, [
            'INSTALLMENT'              => $feeData['angsuran']      ?? 0,
            'EFF_RATE'                 => $feeData['eff_rate']      ?? 0,
            'FLAT_RATE'                => $feeData['flat_rate']     ?? 0,
            'INTEREST_RATE'            => $feeData['suku_bunga']    ?? 0,
            'TOTAL_INTEREST'           => $feeData['total_bunga']   ?? 0,
            'NET_ADMIN'                => $feeData['total']         ?? 0,
            'TOTAL_ADMIN'              => $feeData['total']         ?? ($feeData['admin_fee'] ?? 0),
            'INTEREST_FEE'             => $feeData['bunga_fee']     ?? 0,
            'PROCCESS_FEE'             => $feeData['proses_fee']    ?? 0,
            'POKOK_PEMBAYARAN'         => ($feeData['total'] ?? 0) + ($submissionValue ?? 0),
        ]);

        // Optional extras (added only if available)
        $optionalFields = [
            'CADANGAN'                 => 'cadangan',
            'PAYMENT_WAY'              => 'cara_pembayaran',
            'PROVISION'                => 'provisi',
            'INSURANCE'                => 'asuransi',
            'TRANSFER_FEE'             => 'biaya_transfer',
            'INTEREST_MARGIN'          => 'bunga_margin',
            'PRINCIPAL_MARGIN'         => 'pokok_margin',
            'LAST_INSTALLMENT'         => 'angsuran_terakhir',
            'INTEREST_MARGIN_EFF_ACTUAL' => 'bunga_eff_actual',
            'INTEREST_MARGIN_EFF_FLAT'   => 'bunga_flat',
        ];

        foreach ($optionalFields as $field => $key) {
            $fields[$field] = $ekstra[$key] ?? null;
        }

        $fields['MOD_DATE'] = now()->format('Y-m-d');
        $fields['MOD_BY'] = $user->id;

        $applicationModel->update($fields);
    }

    private function insert_cr_order($request, $id, $fpkId)
    {
        $check = M_CrOrder::where('APPLICATION_ID', $fpkId)->first();

        $data_order = [
            'NO_NPWP' => $request->order['no_npwp'] ?? null,
            'BIAYA' => $request->order['biaya_bulanan'] ?? null,
            'ORDER_TANGGAL' => Carbon::parse($request->order['order_tanggal'])->format('Y-m-d') ?? null,
            'ORDER_STATUS' => $request->order['order_status'] ?? null,
            'ORDER_TIPE' => $request->order['order_tipe'] ?? null,
            'UNIT_BISNIS' => $request->order['unit_bisnis'] ?? null,
            'CUST_SERVICE' => $request->order['cust_service'] ?? null,
            'REF_PELANGGAN' => $request->order['ref_pelanggan'] ?? null,
            'REF_PELANGGAN_OTHER' => $request->order['ref_pelanggan_oth'] ?? null,
            'PROG_MARKETING' => $request->order['prog_marketing'] ?? null,
            'CARA_BAYAR' => $request->order['cara_bayar'] ?? null,
            'KODE_BARANG' => $request->barang_taksasi['kode_barang'] ?? null,
            'ID_TIPE' => $request->barang_taksasi['id_tipe'] ?? null,
            'TAHUN' => $request->barang_taksasi['tahun'] ?? null,
            'HARGA_PASAR' => $request->barang_taksasi['harga_pasar'] ?? null,
            'MOTHER_NAME' => $request->order['nama_ibu'] ?? null,
            'CATEGORY' => $request->order['kategori'] ?? null,
            'TITLE' => $request->order['gelar'] ?? null,
            'WORK_PERIOD'  => $request->order['lama_bekerja'] ?? null,
            'DEPENDANTS'  => $request->order['tanggungan'] ?? null,
            'INCOME_PERSONAL'  => $request->order['pendapatan_pribadi'] ?? null,
            'INCOME_SPOUSE'  => $request->order['pendapatan_pasangan'] ?? null,
            'INCOME_OTHER'  => $request->order['pendapatan_lainnya'] ?? null,
            'EXPENSES'  => $request->order['biaya_bulanan'] ?? null,
            'SURVEY_NOTE' => $request->order['catatan_survey'] ?? null
        ];

        if (!$check) {
            $data_order['ID'] = Uuid::uuid7()->toString();
            $data_order['APPLICATION_ID'] = $fpkId;

            M_CrOrder::create($data_order);
        } else {
            $check->update($data_order);
        }
    }

    private function insert_cr_personal($request, $applicationId)
    {
        $check = M_CrPersonal::where('APPLICATION_ID', $applicationId)->first();

        $data_cr_application = [
            'NAME' => $request->pelanggan['nama'] ?? null,
            'ALIAS' => $request->pelanggan['nama_panggilan'] ?? null,
            'GENDER' => $request->pelanggan['jenis_kelamin'] ?? null,
            'BIRTHPLACE' => $request->pelanggan['tempat_lahir'] ?? null,
            'BIRTHDATE' => $request->pelanggan['tgl_lahir'] ?? null,
            'BLOOD_TYPE' => $request->pelanggan['gol_darah'] ?? null,
            'MARTIAL_STATUS' => $request->pelanggan['status_kawin'] ?? null,
            'MARTIAL_DATE' => $request->pelanggan['tgl_kawin'] ?? null,
            'ID_TYPE' => $request->pelanggan['tipe_identitas'] ?? null,
            'ID_NUMBER' => $request->pelanggan['no_identitas'] ?? null,
            'ID_ISSUE_DATE' => $request->pelanggan['tgl_terbit'] ?? null,
            'ID_VALID_DATE' => $request->pelanggan['masa_berlaku'] ?? null,
            'KK' => $request->pelanggan['no_kk'] ?? null,
            'CITIZEN' => $request->pelanggan['warganegara'] ?? null,

            'ADDRESS' => $request->alamat_identitas['alamat'] ?? null,
            'RT' => $request->alamat_identitas['rt'] ?? null,
            'RW' => $request->alamat_identitas['rw'] ?? null,
            'PROVINCE' => $request->alamat_identitas['provinsi'] ?? null,
            'CITY' => $request->alamat_identitas['kota'] ?? null,
            'KELURAHAN' => $request->alamat_identitas['kelurahan'] ?? null,
            'KECAMATAN' => $request->alamat_identitas['kecamatan'] ?? null,
            'ZIP_CODE' =>  $request->alamat_identitas['kode_pos'] ?? null,

            'INS_ADDRESS' => $request->alamat_tagih['alamat'] ?? null,
            'INS_RT' => $request->alamat_tagih['rt'] ?? null,
            'INS_RW' => $request->alamat_tagih['rw'] ?? null,
            'INS_PROVINCE' => $request->alamat_tagih['provinsi'] ?? null,
            'INS_CITY' => $request->alamat_tagih['kota'] ?? null,
            'INS_KELURAHAN' => $request->alamat_tagih['kelurahan'] ?? null,
            'INS_KECAMATAN' => $request->alamat_tagih['kecamatan'] ?? null,
            'INS_ZIP_CODE' => $request->alamat_tagih['kode_pos'] ?? null,

            'OCCUPATION' => $request->pekerjaan['pekerjaan'] ?? null,
            'OCCUPATION_ON_ID' => $request->pekerjaan['pekerjaan_id'] ?? null,
            'RELIGION' => $request->pekerjaan['agama'] ?? null,
            'EDUCATION' => $request->pekerjaan['pendidikan'] ?? null,
            'PROPERTY_STATUS' => $request->pekerjaan['status_rumah'] ?? null,
            'PHONE_HOUSE' => $request->pekerjaan['telepon_rumah'] ?? null,
            'PHONE_PERSONAL' => $request->pekerjaan['telepon_selular'] ?? null,
            'PHONE_OFFICE' => $request->pekerjaan['telepon_kantor'] ?? null,
            'EXT_1' => $request->pekerjaan['ekstra1'] ?? null,
            'EXT_2' => $request->pekerjaan['ekstra2'] ?? null,

            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now('Asia/Jakarta')->format('Y-m-d'),
            'CREATE_USER' => $request->user()->id,
        ];

        if (!$check) {
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;

            M_CrPersonal::create($data_cr_application);
        } else {
            $check->update($data_cr_application);
        }
    }

    private function insert_cr_guarantor($request, $applicationId)
    {
        if (collect($request->penjamin)->isNotEmpty()) {
            foreach ($request->penjamin as $res) {

                $check = M_CrApplicationGuarantor::where('ID', $res['id'])->first();

                $data_cr_application = [
                    'ID' => $res['id'] ?? null,
                    'NAME' => $res['nama'] ?? null,
                    'GENDER' => $res['jenis_kelamin'] ?? null,
                    'BIRTHPLACE' => $res['tempat_lahir'] ?? null,
                    'BIRTHDATE' => $res['tgl_lahir'] ?? null,
                    'ADDRESS' => $res['alamat'] ?? null,
                    'IDENTITY_TYPE' => $res['tipe_identitas'] ?? null,
                    'NUMBER_IDENTITY' => $res['no_identitas'] ?? null,
                    'OCCUPATION' => $res['pekerjaan'] ?? null,
                    'WORK_PERIOD' => $res['lama_bekerja'] ?? null,
                    'STATUS_WITH_DEBITUR' => $res['hub_cust'] ?? null,
                    'MOBILE_NUMBER' => $res['no_hp'] ?? null,
                    'INCOME' => $res['pendapatan'] ?? null,
                ];

                if (!$check) {
                    $data_cr_application['ID'] = $res['id'];
                    $data_cr_application['APPLICATION_ID'] = $applicationId;

                    M_CrApplicationGuarantor::create($data_cr_application);
                } else {
                    $check->update($data_cr_application);
                }
            }
        }
    }

    private function insert_cr_spouse($request, $applicationId)
    {

        $check = M_CrApplicationSpouse::where('APPLICATION_ID', $applicationId)->first();

        $data_cr_application = [
            'NAME' => $request->pasangan['nama_pasangan'] ?? null,
            'BIRTHPLACE' => $request->pasangan['tmptlahir_pasangan'] ?? null,
            'BIRTHDATE' => $request->pasangan['tgllahir_pasangan'] ?? null,
            'ADDRESS' => $request->pasangan['alamat_pasangan'] ?? null,
            'OCCUPATION' => $request->pasangan['pekerjaan_pasangan'] ?? null
        ];

        if (!$check) {
            $data_cr_application['ID'] = Uuid::uuid7()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;

            M_CrApplicationSpouse::create($data_cr_application);
        } else {
            $check->update($data_cr_application);
        }
    }

    private function insert_cr_personal_extra($request, $applicationId)
    {

        $check = M_CrPersonalExtra::where('APPLICATION_ID', $applicationId)->first();

        $data_cr_application = [
            'BI_NAME' => $request->tambahan['nama_bi'] ?? null,
            'EMAIL' => $request->tambahan['email'] ?? null,
            'INFO' => $request->tambahan['info_khusus'] ?? null,
            'OTHER_OCCUPATION_1' => $request->tambahan['usaha_lain1'] ?? null,
            'OTHER_OCCUPATION_2' => $request->tambahan['usaha_lain2'] ?? null,
            'OTHER_OCCUPATION_3' => $request->tambahan['usaha_lain3'] ?? null,
            'OTHER_OCCUPATION_4' => $request->tambahan['usaha_lain4'] ?? null,
            'MAIL_ADDRESS' => $request->surat['alamat'] ?? null,
            'MAIL_RT' => $request->surat['rt'] ?? null,
            'MAIL_RW' => $request->surat['rw'] ?? null,
            'MAIL_PROVINCE' => $request->surat['provinsi'] ?? null,
            'MAIL_CITY' => $request->surat['kota'] ?? null,
            'MAIL_KELURAHAN' => $request->surat['kelurahan'] ?? null,
            'MAIL_KECAMATAN' => $request->surat['kecamatan'] ?? null,
            'MAIL_ZIP_CODE' => $request->surat['kode_pos'] ?? null,
            'EMERGENCY_NAME' => $request->kerabat_darurat['nama'] ?? null,
            'EMERGENCY_ADDRESS' => $request->kerabat_darurat['alamat'] ?? null,
            'EMERGENCY_RT' => $request->kerabat_darurat['rt'] ?? null,
            'EMERGENCY_RW' => $request->kerabat_darurat['rw'] ?? null,
            'EMERGENCY_PROVINCE' => $request->kerabat_darurat['provinsi'] ?? null,
            'EMERGENCY_CITY' => $request->kerabat_darurat['kota'] ?? null,
            'EMERGENCY_KELURAHAN' => $request->kerabat_darurat['kelurahan'] ?? null,
            'EMERGENCY_KECAMATAN' => $request->kerabat_darurat['kecamatan'] ?? null,
            'EMERGENCY_ZIP_CODE' => $request->kerabat_darurat['kode_pos'] ?? null,
            'EMERGENCY_PHONE_HOUSE' => $request->kerabat_darurat['no_telp'] ?? null,
            'EMERGENCY_PHONE_PERSONAL'  => $request->kerabat_darurat['no_hp'] ?? null
        ];

        if (!$check) {
            $data_cr_application['ID'] = Uuid::uuid4()->toString();
            $data_cr_application['APPLICATION_ID'] = $applicationId;

            M_CrPersonalExtra::create($data_cr_application);
        } else {
            $check->update($data_cr_application);
        }
    }

    private function insert_bank_account($request, $applicationId)
    {

        if (isset($request->info_bank) && is_array($request->info_bank)) {

            M_CrApplicationBank::where('APPLICATION_ID', $applicationId)->delete();

            $dataToInsert = [];
            foreach ($request->info_bank as $result) {
                $dataToInsert[] = [
                    'ID' => Uuid::uuid4()->toString(),
                    'APPLICATION_ID' => $applicationId,
                    'BANK_CODE' => $result['kode_bank'] ?? null,
                    'BANK_NAME' => $result['nama_bank'] ?? null,
                    'ACCOUNT_NUMBER' => $result['no_rekening'] ?? null,
                    'ACCOUNT_NAME' => $result['atas_nama'] ?? null,
                    'STATUS' => $result['status'] ?? null,
                ];
            }

            M_CrApplicationBank::insert($dataToInsert);
        }
    }

    private function insert_application_approval($request, $applicationId, $surveyID, $flag)
    {
        if ($flag === 'yes') {
            $data_approval['code'] = 'WAKPS';
            $data_approval['application_result'] = 'menunggu kapos';

            $data_application_log = [
                'CODE' => 'WAKPS',
                'POSITION' => 'ADMIN',
                'APPLICATION_APPROVAL_ID' => $applicationId,
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
                'APPROVAL_RESULT' => 'menunggu kapos'
            ];

            M_ApplicationApprovalLog::create($data_application_log);

            $survey_apprval_change = M_SurveyApproval::where('CR_SURVEY_ID', $surveyID)->first();

            if ($survey_apprval_change) {
                $data_update_approval = [
                    'CODE' => 'WAKPS',
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
                    'APPROVAL_RESULT' => 'menunggu kapos'
                ];

                $survey_apprval_change->update($data_update_approval);

                $data_survey_log = [
                    'CODE' => 'WAKPS',
                    'SURVEY_APPROVAL_ID' => $surveyID,
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
                    'APPROVAL_RESULT' =>  'menunggu kapos'
                ];

                M_SurveyApprovalLog::create($data_survey_log);
            }
        } else {
            $data_approval['code'] = 'DROR';
            $data_approval['application_result'] = 'draf';
        }

        $checkApproval = M_ApplicationApproval::where('cr_application_id', $applicationId)->first();

        if (!$checkApproval) {
            $data_approval = array_merge(
                [
                    'id' => Uuid::uuid7()->toString(),
                    'cr_prospect_id' => $surveyID,
                    'cr_application_id' => $applicationId
                ],
                $data_approval
            );

            M_ApplicationApproval::create($data_approval);
        } else {
            $checkApproval->update($data_approval);
        }
    }

    public function generateUuidFPK(Request $request)
    {
        DB::beginTransaction();
        try {
            $getSurveyId = $request->cr_prospect_id;

            $check_survey_id = M_CrSurvey::where('id', $getSurveyId)->whereNull('deleted_at')->first();

            if (!$check_survey_id) {
                throw new Exception("Id Survey Is Not Exist", 404);
            }

            $uuid = Uuid::uuid7()->toString();

            $check_prospect_id = M_CrApplication::where('CR_SURVEY_ID', $getSurveyId)->first();

            if (!$check_prospect_id) {
                $generate_uuid = $uuid;

                $data_cr_application = [
                    'ID' => $uuid,
                    'CR_SURVEY_ID' => $check_survey_id->id,
                    'ORDER_NUMBER' => $this->createAutoCode(M_CrApplication::class, 'ORDER_NUMBER', 'COR'),
                    'BRANCH' => $request->user()->branch_id,
                    'TENOR' => $check_survey_id->tenor ?? null,
                    'INSTALLMENT_TYPE' => $check_survey_id->jenis_angsuran ?? null,
                    'INSTALLMENT' => $check_survey_id->angsuran ?? 0,
                    'VERSION' => 1,
                    'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                    'CREATE_BY' => $request->user()->id,
                ];

                M_CrApplication::create($data_cr_application);

                if (strtolower($check_survey_id->category) == 'ro') {

                    $customer = M_Customer::where('ID_NUMBER', $check_survey_id->ktp)->first();
                    $customer_xtra = M_CustomerExtra::where('CUST_CODE', $customer->CUST_CODE)->first();

                    $data_cr_personal = [
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'NAME' => $check_survey_id->nama ?? null,
                        'ALIAS' => $customer->ALIAS ?? null,
                        'GENDER' => $customer->GENDER ?? null,
                        'BIRTHPLACE' => $customer->BIRTHPLACE ?? null,
                        'BIRTHDATE' => $check_survey_id->tgl_lahir ?? null,
                        'BLOOD_TYPE' => $customer->BLOOD_TYPE ?? null,
                        'MARTIAL_STATUS' => $customer->MARTIAL_STATUS ?? null,
                        'MARTIAL_DATE' => $customer->MARTIAL_DATE ?? null,
                        'ID_TYPE' => $customer->ID_TYPE ?? null,
                        'ID_NUMBER' => $check_survey_id->ktp ?? null,
                        'ID_ISSUE_DATE' => $customer->ID_ISSUE_DATE ?? null,
                        'ID_VALID_DATE' => $customer->ID_VALID_DATE ?? null,
                        'KK' => $check_survey_id->kk ?? null,
                        'CITIZEN' => $customer->CITIZEN ?? null,

                        'ADDRESS' => $check_survey_id->alamat ?? null,
                        'RT' => $check_survey_id->rt ?? null,
                        'RW' => $check_survey_id->rw ?? null,
                        'PROVINCE' => $customer->PROVINCE ?? null,
                        'CITY' => $customer->CITY ?? null,
                        'KELURAHAN' => $customer->KELURAHAN ?? null,
                        'KECAMATAN' => $customer->KECAMATAN ?? null,
                        'ZIP_CODE' => $customer->ZIP_CODE ?? null,

                        'INS_ADDRESS' => $customer->INS_ADDRESS ?? null,
                        'INS_RT' => $customer->INS_RT ?? null,
                        'INS_RW' => $customer->INS_RW ?? null,
                        'INS_PROVINCE' => $customer->INS_PROVINCE ?? null,
                        'INS_CITY' => $customer->INS_CITY ?? null,
                        'INS_KELURAHAN' => $customer->INS_KELURAHAN ?? null,
                        'INS_KECAMATAN' => $customer->INS_KECAMATAN ?? null,
                        'INS_ZIP_CODE' => $customer->INS_ZIP_CODE ?? null,

                        'OCCUPATION' => $customer->OCCUPATION ?? null,
                        'OCCUPATION_ON_ID' => $customer->OCCUPATION_ON_ID ?? null,
                        'RELIGION' => $customer->RELIGION ?? null,
                        'EDUCATION' => $customer->EDUCATION ?? null,
                        'PROPERTY_STATUS' => $customer->PROPERTY_STATUS ?? null,
                        'PHONE_HOUSE' => $customer->PHONE_HOUSE ?? null,
                        'PHONE_PERSONAL' => $customer->PHONE_PERSONAL ?? null,
                        'PHONE_OFFICE' => $customer->PHONE_OFFICE ?? null,
                        'EXT_1' => $customer->EXT_1 ?? null,
                        'EXT_2' => $customer->EXT_2 ?? null,
                        'VERSION' => 1,
                        'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                        'CREATE_USER' => $request->user()->id,
                    ];

                    $data_cr_order = [
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'NO_NPWP' => $customer->NPWP ?? null,
                        'MOTHER_NAME' => $customer->MOTHER_NAME ?? null,
                        'WORK_PERIOD'  => $check_survey_id->work_period ?? null,
                        'INCOME_PERSONAL'  => $check_survey_id->income_personal ?? null,
                        'INCOME_SPOUSE'  => $check_survey_id->income_spouse ?? null,
                        'INCOME_OTHER'  => $check_survey_id->income_other ?? null,
                        'UNIT_BISNIS' => $check_survey_id->usaha ?? null,
                        'EXPENSES'  =>  $check_survey_id->expenses ?? null,
                    ];

                    $data_cr_application_spouse = [
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'NAME' => $customer_xtra->SPOUSE_NAME ?? null,
                        'BIRTHPLACE' => $customer_xtra->SPOUSE_BIRTHPLACE ?? null,
                        'BIRTHDATE' => $customer_xtra->SPOUSE_BIRTHDATE ?? null,
                        'ADDRESS' => $customer_xtra->SPOUSE_ADDRESS ?? null,
                        'OCCUPATION' => $customer_xtra->SPOUSE_OCCUPATION ?? null
                    ];

                    $data_cr_personal_extra = [
                        'ID' => Uuid::uuid7()->toString(),
                        'APPLICATION_ID' => $uuid,
                        'EMERGENCY_NAME' => $customer_xtra->EMERGENCY_NAME ?? null,
                        'EMERGENCY_ADDRESS' => $customer_xtra->EMERGENCY_ADDRESS ?? null,
                        'EMERGENCY_RT' => $customer_xtra->EMERGENCY_RT ?? null,
                        'EMERGENCY_RW' => $customer_xtra->EMERGENCY_RW ?? null,
                        'EMERGENCY_PROVINCE' => $customer_xtra->EMERGENCY_PROVINCE ?? null,
                        'EMERGENCY_CITY' => $customer_xtra->EMERGENCYL_CITY ?? null,
                        'EMERGENCY_KELURAHAN' => $customer_xtra->EMERGENCY_KELURAHAN ?? null,
                        'EMERGENCY_KECAMATAN' => $customer_xtra->EMERGENCYL_KECAMATAN ?? null,
                        'EMERGENCY_ZIP_CODE' => $customer_xtra->EMERGENCY_ZIP_CODE ?? null,
                        'EMERGENCY_PHONE_HOUSE' => $customer_xtra->EMERGENCY_PHONE_HOUSE ?? null,
                        'EMERGENCY_PHONE_PERSONAL' => $customer_xtra->EMERGENCY_PHONE_PERSONAL ?? null
                    ];

                    M_CrPersonal::create($data_cr_personal);
                    M_CrPersonalExtra::create($data_cr_personal_extra);
                    M_CrOrder::create($data_cr_order);
                    M_CrApplicationSpouse::create($data_cr_application_spouse);
                }

                $this->createApplicationApproval($request, $getSurveyId, $generate_uuid);
            } else {
                $generate_uuid = $check_prospect_id->ID;
            }

            DB::commit();
            return response()->json(['message' => 'OK', "status" => 200, 'response' => ['uuid' => $generate_uuid]], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    private function createApplicationApproval($request, $getSurveyId, $fpkCreate = null)
    {
        $uuid = Uuid::uuid7()->toString();

        $approval = [
            'id' => $uuid,
            'code' => 'CROR',
            'cr_prospect_id' => $getSurveyId,
            'cr_application_id' => $fpkCreate,
            'application_result' => 'order diproses'
        ];

        M_ApplicationApproval::create($approval);

        $data_application_log = [
            'CODE' => 'CROR',
            'POSITION' => 'ADMIN',
            'APPLICATION_APPROVAL_ID' => $uuid,
            'ONCHARGE_PERSON' => $request->user()->id,
            'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
            'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
            'APPROVAL_RESULT' => 'order diproses'
        ];

        M_ApplicationApprovalLog::create($data_application_log);

        $survey_apprval_change = M_SurveyApproval::where('CR_SURVEY_ID', $getSurveyId)->first();

        $data_update_approval = [
            'CODE' => 'CROR',
            'ONCHARGE_PERSON' => $request->user()->id,
            'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
            'APPROVAL_RESULT' => 'order diproses'
        ];

        $survey_apprval_change->update($data_update_approval);

        $data_survey_log = [
            'CODE' => 'CROR',
            'SURVEY_APPROVAL_ID' => $getSurveyId,
            'ONCHARGE_PERSON' => $request->user()->id,
            'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
            'APPROVAL_RESULT' =>  'order diproses'
        ];

        M_SurveyApprovalLog::create($data_survey_log);
    }

    private function resourceDetail($data, $application)
    {
        $surveyId = $data->id;
        $setApplicationId = $application->ID;

        $cr_survey = M_CrSurvey::where('id', $surveyId)->first();
        $applicationDetail = M_CrApplication::where('ID', $setApplicationId)->first();
        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $setApplicationId)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID', $setApplicationId)->first();
        $cr_oder = M_CrOrder::where('APPLICATION_ID', $setApplicationId)->first();
        $cr_guarantor = M_CrApplicationGuarantor::where('APPLICATION_ID', $setApplicationId)->get();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID', $setApplicationId)->first();
        $approval = M_ApplicationApproval::where('cr_application_id', $setApplicationId)->first();
        $check_exist = M_Credit::where('ORDER_NUMBER', $application->ORDER_NUMBER)->first();

        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $surveyId)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $guarente_sertificat = M_CrGuaranteSertification::where('CR_SURVEY_ID', $surveyId)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $ktp = empty($cr_personal->ID_NUMBER) ? $data->ktp ?? null : $cr_personal->ID_NUMBER ?? null;
        $kk = empty($cr_personal->KK) ? $cr_survey->kk : $cr_personal->KK;
        $orderNumber = $application->ORDER_NUMBER;

        $arrayList = [
            'id_application' => $setApplicationId,
            'survey_id' => $surveyId,
            'order_number' => $orderNumber,
            "flag" => !$check_exist ? 0 : 1,
            'jenis_angsuran' => empty($application->INSTALLMENT_TYPE) ? $cr_survey->jenis_angsuran ?? '' : $application->INSTALLMENT_TYPE ?? '',
            'pelanggan' => [
                "nama" => $cr_personal->NAME ?? ($data->nama ?? ''),
                "nama_panggilan" => $cr_personal->ALIAS ?? null,
                "jenis_kelamin" => $cr_personal->GENDER ?? null,
                "tempat_lahir" => $cr_personal->BIRTHPLACE ?? null,
                "tgl_lahir" => date_format(date_create(empty($cr_personal->BIRTHDATE) ? $cr_survey->tgl_lahir : $cr_personal->BIRTHDATE), 'Y-m-d'),
                "gol_darah" => $cr_personal->BLOOD_TYPE ?? null,
                "status_kawin" => $cr_personal->MARTIAL_STATUS ?? null,
                "tgl_kawin" => $cr_personal->MARTIAL_DATE ?? null,
                "tipe_identitas" => "KTP",
                "no_identitas" => empty($cr_personal->ID_NUMBER) ? $data->ktp ?? null : $cr_personal->ID_NUMBER ?? null,
                "tgl_terbit_identitas" => $cr_personal->ID_ISSUE_DATE ?? null,
                "masa_berlaku_identitas" => $cr_personal->ID_VALID_DATE ?? null,
                "no_kk" => empty($cr_personal->KK) ? $cr_survey->kk : $cr_personal->KK,
                "warganegara" => $cr_personal->CITIZEN ?? null
            ],
            'alamat_identitas' => [
                "alamat" => empty($cr_personal->ADDRESS) ? $cr_survey->alamat ?? null : $cr_personal->ADDRESS ?? null,
                "rt" => empty($cr_personal->RT) ? $cr_survey->rt ?? null : $cr_personal->RT ?? null,
                "rw" => empty($cr_personal->RW) ? $cr_survey->rw ?? null : $cr_personal->RW ?? null,
                "provinsi" => empty($cr_personal->PROVINCE) ? $cr_survey->province ?? null : $cr_personal->PROVINCE ?? null,
                "kota" => empty($cr_personal->CITY) ? $cr_survey->city ?? null : $cr_personal->CITY ?? null,
                "kelurahan" => empty($cr_personal->KELURAHAN) ? $cr_survey->kelurahan ?? null : $cr_personal->KELURAHAN ?? null,
                "kecamatan" => empty($cr_personal->KECAMATAN) ? $cr_survey->kecamatan ?? null : $cr_personal->KECAMATAN ?? null,
                "kode_pos" => empty($cr_personal->ZIP_CODE) ? $cr_survey->zip_code ?? null : $cr_personal->ZIP_CODE ?? null
            ],
            'alamat_tagih' => [
                "alamat" => $cr_personal->INS_ADDRESS ?? null,
                "rt" => $cr_personal->INS_RT ?? null,
                "rw" => $cr_personal->INS_RW ?? null,
                "provinsi" => $cr_personal->INS_PROVINCE ?? null,
                "kota" => $cr_personal->INS_CITY ?? null,
                "kelurahan" => $cr_personal->INS_KELURAHAN ?? null,
                "kecamatan" => $cr_personal->INS_KECAMATAN ?? null,
                "kode_pos" => $cr_personal->INS_ZIP_CODE ?? null
            ],
            "barang_taksasi" => [
                "kode_barang" => $cr_oder->KODE_BARANG ?? null,
                "id_tipe" => $cr_oder->ID_TIPE ?? null,
                "tahun" => $cr_oder->TAHUN ?? null,
                "harga_pasar" => $cr_oder->HARGA_PASAR ?? null
            ],
            'pekerjaan' => [
                "pekerjaan" => empty($cr_personal->OCCUPATION) ? $cr_survey->usaha ?? null : $cr_personal->OCCUPATION ?? null,
                "pekerjaan_id" => empty($cr_personal->OCCUPATION_ON_ID) ? $cr_survey->sector : $cr_personal->OCCUPATION_ON_ID ?? null,
                "agama" => $cr_personal->RELIGION ?? null,
                "pendidikan" => $cr_personal->EDUCATION ?? null,
                "status_rumah" => $cr_personal->PROPERTY_STATUS ?? null,
                "telepon_rumah" => $cr_personal->PHONE_HOUSE ?? null,
                "telepon_selular" => empty($cr_personal->PHONE_PERSONAL) ? $data->hp ?? null : $cr_personal->PHONE_PERSONAL ?? null,
                "telepon_kantor" => $cr_personal->PHONE_OFFICE ?? null,
                "ekstra1" => $cr_personal->EXT_1 ?? null,
                "ekstra2" => $cr_personal->EXT_2 ?? null
            ],
            'order' => [
                "nama_ibu" => $cr_oder->MOTHER_NAME ?? null,
                'cr_prospect_id' => $prospect_id ?? null,
                "kategori" => $cr_oder->CATEGORY ?? null,
                "gelar" => $cr_oder->TITLE ?? null,
                "lama_bekerja" => empty($cr_oder->WORK_PERIOD) ? intval($cr_survey->work_period) : intval($cr_oder->WORK_PERIOD),
                "tanggungan" => $cr_oder->DEPENDANTS ?? null,
                "biaya_bulanan" => intval(empty($cr_oder->BIAYA) ? $cr_survey->expenses : $cr_oder->BIAYA),
                "pendapatan_pribadi" => intval(empty($cr_oder->INCOME_PERSONAL) ? $cr_survey->income_personal : $cr_oder->INCOME_PERSONAL),
                "pendapatan_pasangan" => intval(empty($cr_oder->INCOME_SPOUSE) ? $cr_survey->income_spouse : $cr_oder->INCOME_SPOUSE),
                "pendapatan_lainnya" => intval(empty($cr_oder->INCOME_OTHER) ? $cr_survey->income_other : $cr_oder->INCOME_OTHER),
                "no_npwp" => $cr_oder->NO_NPWP ?? null,
                "order_tanggal" =>  date('d-m-Y', strtotime($cr_survey->visit_date)) ?? null,
                "order_status" =>  $cr_oder->ORDER_STATUS ?? null,
                "order_tipe" =>  $cr_oder->ORDER_TIPE ?? null,
                "unit_bisnis" => $cr_oder->UNIT_BISNIS ?? null,
                "cust_service" => $cr_oder->CUST_SERVICE ?? null,
                "ref_pelanggan" => $cr_oder->REF_PELANGGAN ?? null,
                'ref_pelanggan_oth' => $cr_oder->REF_PELANGGAN_OTHER ?? null,
                "surveyor_name" => User::find($cr_survey->created_by)->fullname,
                "catatan_survey" => !empty($cr_oder->SURVEY_NOTE) ? $cr_oder->SURVEY_NOTE : $data->survey_note ?? null,
                "prog_marketing" => $cr_oder->PROG_MARKETING ?? null,
                "cara_bayar" => $cr_oder->CARA_BAYAR ?? null
            ],
            'tambahan' => [
                "nama_bi"  => $cr_personal_extra->BI_NAME ?? null,
                "email"  => $cr_personal_extra->EMAIL ?? null,
                "info_khusus"  => $cr_personal_extra->INFO ?? null,
                "usaha_lain1"  => $cr_personal_extra->OTHER_OCCUPATION_1 ?? null,
                "usaha_lain2"  => $cr_personal_extra->OTHER_OCCUPATION_2 ?? null,
                "usaha_lain3"  => $cr_personal_extra->OTHER_OCCUPATION_3 ?? null,
                "usaha_lain4"  => $cr_personal_extra->OTHER_OCCUPATION_4 ?? null,
            ],
            'kerabat_darurat' => [
                "nama"  => $cr_personal_extra->EMERGENCY_NAME ?? null,
                "alamat"  => $cr_personal_extra->EMERGENCY_ADDRESS ?? null,
                "rt"  => $cr_personal_extra->EMERGENCY_RT ?? null,
                "rw"  => $cr_personal_extra->EMERGENCY_RW ?? null,
                "provinsi" => $cr_personal_extra->EMERGENCY_PROVINCE ?? null,
                "kota" => $cr_personal_extra->EMERGENCY_CITY ?? null,
                "kelurahan" => $cr_personal_extra->EMERGENCY_KELURAHAN ?? null,
                "kecamatan" => $cr_personal_extra->EMERGENCY_KECAMATAN ?? null,
                "kode_pos" => $cr_personal_extra->EMERGENCY_ZIP_CODE ?? null,
                "no_telp" => $cr_personal_extra->EMERGENCY_PHONE_HOUSE ?? null,
                "no_hp" => $cr_personal_extra->EMERGENCY_PHONE_PERSONAL ?? null,
            ],
            "penjamin" => [],
            "pasangan" => [
                "nama_pasangan" => $cr_spouse->NAME ?? null,
                "tmptlahir_pasangan" => $cr_spouse->BIRTHPLACE ?? null,
                "pekerjaan_pasangan" => $cr_spouse->OCCUPATION ?? null,
                "tgllahir_pasangan" => $cr_spouse->BIRTHDATE ?? null,
                "alamat_pasangan" => $cr_spouse->ADDRESS ?? null
            ],
            "info_bank" => [],
            "ekstra" => [
                'jenis_angsuran' => strtolower(empty($application->INSTALLMENT_TYPE) ? $cr_survey->jenis_angsuran : $application->INSTALLMENT_TYPE),
                'tenor' => (int) $application->TENOR,
                "nilai_yang_diterima" => $applicationDetail->SUBMISSION_VALUE == '' ? (int) $data->plafond : (int)$applicationDetail->SUBMISSION_VALUE ?? null,
                "total" => (int)$applicationDetail->TOTAL_ADMIN ?? null,
                "cadangan" => $applicationDetail->CADANGAN ?? null,
                "opt_periode" => $applicationDetail->OPT_PERIODE ?? null,
                "provisi" => $applicationDetail->PROVISION ?? null,
                "asuransi" => $applicationDetail->INSURANCE ?? null,
                "biaya_transfer" => $applicationDetail->TRANSFER_FEE ?? null,
                "eff_rate" => $applicationDetail->EFF_RATE ?? null,
                "angsuran" => intval($applicationDetail->INSTALLMENT) ?? null
            ],
            'jaminan' => [],
            "prospect_approval" => [
                "status" => $approval->application_result == null ? $approval->application_result : ""
            ],
            "dokumen_indentitas" => $this->attachment($surveyId, "'ktp', 'kk', 'ktp_pasangan','selfie'"),
            "dokumen_jaminan" => $this->attachment($surveyId, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'"),
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($surveyId, ['other']) ?? null,
            "dokumen_order" => $this->attachment($surveyId, "'sp', 'pk', 'dok'"),
            "approval" =>
            [
                'status' => $approval->application_result ?? null,
                'kapos' => $approval->cr_application_kapos_desc ?? null,
                'ho' => $approval->cr_application_ho_desc ?? null
            ],
            "order_validation" => $this->validationService->validate(
                [
                    "OrderNumber" => $orderNumber,
                    "KTP"          => $ktp,
                    "KK"           => $kk,
                ],
                $guarente_vehicle
            )
        ];

        $arrayList['info_bank'] = M_CrApplicationBank::where('APPLICATION_ID', $application->ID)
            ->get()
            ->map(function ($list) {
                return [
                    "kode_bank" => $list->BANK_CODE,
                    "nama_bank" => $list->BANK_NAME,
                    "no_rekening" => $list->ACCOUNT_NUMBER,
                    "atas_nama" => $list->ACCOUNT_NAME,
                    "status" => $list->STATUS
                ];
            })
            ->all();

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan'][] = [
                "type" => "kendaraan",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    'kondisi_jaminan' => $list->POSITION_FLAG,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "alamat_bpkb" => $list->BPKB_ADDRESS,
                    "no_faktur" => $list->INVOICE_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    "document" => $this->attachment_guarante($surveyId, $list->HEADER_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'")
                ]
            ];
        }

        foreach ($cr_guarantor as $list) {
            $arrayList['penjamin'][] = [
                "id" => $list->ID ?? null,
                "nama" => $list->NAME ?? null,
                "jenis_kelamin" => $list->GENDER ?? null,
                "tempat_lahir" => $list->BIRTHPLACE ?? null,
                "tgl_lahir" => $list->BIRTHDATE ?? null,
                "alamat" => $list->ADDRESS ?? null,
                "tipe_identitas"  => $list->IDENTIY_TYPE ?? null,
                "no_identitas"  => $list->NUMBER_IDENTITY ?? null,
                "pekerjaan"  => $list->OCCUPATION ?? null,
                "lama_bekerja"  => intval($list->WORK_PERIOD) ?? null,
                "hub_cust" => $list->STATUS_WITH_DEBITUR ?? null,
                "no_hp" => $list->MOBILE_NUMBER ?? null,
                "pendapatan" => $list->INCOME ?? null,
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
                    "document" => M_CrSurveyDocument::attachmentSertifikat($surveyId, $list->HEADER_ID, ['sertifikat']) ?? null,
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

    private function attachment_guarante($survey_id, $header_id, $data)
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

    private function insert_taksasi($request, $id)
    {

        $check = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $id)->first();

        $data_order = [
            'TYPE' => $request->barang_taksasi['tipe'] ?? null,
            'BRAND' => $request->barang_taksasi['merk'] ?? null,
            'PRODUCTION_YEAR' => $request->barang_taksasi['tahun'] ?? null,
            'COLOR' => $request->barang_taksasi['warna'] ?? null,
            'ON_BEHALF' => $request->barang_taksasi['atas_nama'] ?? null,
            'POLICE_NUMBER' => $request->barang_taksasi['no_polisi'] ?? null,
            'CHASIS_NUMBER' => $request->barang_taksasi['no_rangka'] ?? null,
            'ENGINE_NUMBER' => $request->barang_taksasi['no_mesin'] ?? null,
            'STNK_NUMBER' => $request->barang_taksasi['no_stnk'] ?? null,
            'STNK_VALID_DATE' => $request->barang_taksasi['tgl_stnk'] ?? null,
            'VALUE' => $request->barang_taksasi['nilai'] ?? null,
            'BPKB_NUMBER' => $request->barang_taksasi['no_bpkb'] ?? null
        ];

        if ($check) {
            $check->update($data_order);
        }
    }

    public function approvalKapos(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'cr_application_id' => 'required|string',
                'flag' => 'required|string',
            ]);

            // Fetch necessary records
            $check_application_id = M_ApplicationApproval::where('cr_application_id', $request->cr_application_id)->first();
            if (!$check_application_id) {
                throw new Exception("Id FPK is not found.", 404);
            }

            $checkApplication = M_CrApplication::where('ID', $request->cr_application_id)->first();
            $surveyApproval = M_SurveyApproval::where('CR_SURVEY_ID', $checkApplication->CR_SURVEY_ID)->first();

            // Prepare common data
            $currentTime = Carbon::now('Asia/Jakarta');
            $userId = $request->user()->id;
            $description = $request->keterangan;
            $flag = $request->flag;

            // Define mapping for flag to code and result
            $approvalDataMap = [
                'yes' => ['code' => 'WAHO', 'result' => 'disetujui,menunggu ho'],
                'revisi' => ['code' => 'REORKPS', 'result' => 'ada revisi kapos'],
                'no' => ['code' => 'CLKPS', 'result' => 'dibatalkan kapos'],
            ];

            // Get corresponding data based on flag
            $approvalData = $approvalDataMap[$flag] ?? $approvalDataMap['no'];

            // Prepare approval data for the application
            $data_approval = [
                'cr_application_kapos' => $userId,
                'cr_application_kapos_time' => $currentTime->format('Y-m-d'),
                'cr_application_kapos_desc' => $description,
                'cr_application_kapos_note' => $flag,
                'code' => $approvalData['code'],
                'application_result' => $approvalData['result'],
            ];

            // Create logs and update the status
            $this->createApplicationApprovalLog($approvalData['code'], $request->cr_application_id, $userId, $currentTime, $description, $approvalData['result'], 'KAPOS');
            $this->updateSurveyApproval($surveyApproval, $approvalData['code'], $userId, $currentTime, $approvalData['result']);
            $this->createSurveyApprovalLog($approvalData['code'], $checkApplication->CR_SURVEY_ID, $userId, $currentTime, $approvalData['result']);

            // Update application approval data
            $check_application_id->update($data_approval);

            // Return success response
            return response()->json(['message' => 'Approval Kapos Successfully', 'status' => 200], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
        }
    }

    public function approvalHo(Request $request)
    {
        try {
            $request->validate([
                'cr_application_id' => 'required|string',
                'flag' => 'required|string',
            ]);

            $check_application_id = M_ApplicationApproval::where('cr_application_id', $request->cr_application_id)->first();

            if (!$check_application_id) {
                throw new Exception("Id FPK Is Not Exist", 404);
            }

            $checkApplication = M_CrApplication::where('ID', $request->cr_application_id)->first();
            $surveyApproval = M_SurveyApproval::where('CR_SURVEY_ID', $checkApplication->CR_SURVEY_ID)->first();

            $currentTime = Carbon::now('Asia/Jakarta');
            $userId = $request->user()->id;
            $description = $request->keterangan;
            $flag = $request->flag;

            // Define mapping for flag to code and result
            $approvalDataMap = [
                'yes' => ['code' => 'APHO', 'result' => 'disetujui ho'],
                'revisi' => ['code' => 'REORHO', 'result' => 'ada revisi ho'],
                'no' => ['code' => 'CLHO', 'result' => 'dibatalkan ho'],
            ];

            // Get corresponding data based on flag
            $approvalData = $approvalDataMap[$flag] ?? $approvalDataMap['no'];

            // Prepare approval data for the application
            $data_approval = [
                'cr_application_ho' => $userId,
                'cr_application_ho_time' => $currentTime->format('Y-m-d'),
                'cr_application_ho_desc' => $description,
                'cr_application_ho_note' => $flag,
                'code' => $approvalData['code'],
                'application_result' => $approvalData['result'],
            ];

            // Create logs and update the status
            $this->createApplicationApprovalLog($approvalData['code'], $request->cr_application_id, $userId, $currentTime, $description, $approvalData['result'], 'HO');
            $this->updateSurveyApproval($surveyApproval, $approvalData['code'], $userId, $currentTime, $approvalData['result']);
            $this->createSurveyApprovalLog($approvalData['code'], $checkApplication->CR_SURVEY_ID, $userId, $currentTime, $approvalData['result']);

            // Update application approval data
            $check_application_id->update($data_approval);

            return response()->json(['message' => 'Approval Ho Successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    private function createApplicationApprovalLog($code, $applicationId, $userId, $currentTime, $description, $result, $position)
    {
        M_ApplicationApprovalLog::create([
            'CODE' => $code,
            'POSITION' => $position,
            'APPLICATION_APPROVAL_ID' => $applicationId,
            'ONCHARGE_PERSON' => $userId,
            'ONCHARGE_TIME' => $currentTime,
            'ONCHARGE_DESCR' => $description,
            'APPROVAL_RESULT' => $result,
        ]);
    }

    private function updateSurveyApproval($surveyApproval, $code, $userId, $currentTime, $result)
    {
        $surveyApproval->update([
            'CODE' => $code,
            'ONCHARGE_PERSON' => $userId,
            'ONCHARGE_TIME' => $currentTime,
            'APPROVAL_RESULT' => $result,
        ]);
    }

    private function createSurveyApprovalLog($code, $surveyId, $userId, $currentTime, $result)
    {
        M_SurveyApprovalLog::create([
            'CODE' => $code,
            'SURVEY_APPROVAL_ID' => $surveyId,
            'ONCHARGE_PERSON' => $userId,
            'ONCHARGE_TIME' => $currentTime,
            'APPROVAL_RESULT' => $result,
        ]);
    }

    public function check_order_document(Request $request)
    {
        $loan_number = $request->loan_number === 'undefined' ? null : $request->loan_number;
        $atas_nama   = $request->atas_nama === 'undefined' ? null : $request->atas_nama;
        $cabang      = $request->cabang === 'undefined' ? null : $request->cabang;
        $dari = $request->dari;
        $sampai = $request->sampai;

        $results = M_Credit::select([
            'ID',
            'LOAN_NUMBER',
            'ORDER_NUMBER',
            'CUST_CODE',
            'INSTALLMENT_DATE',
            'BRANCH',
            'CREATED_AT'
        ])
            ->with([
                'customer:ID,CUST_CODE,NAME',
                'customer.customer_document',
                'collateral:ID,CR_CREDIT_ID,POLICE_NUMBER',
                'collateral.documents',
                'branch:ID,NAME'
            ])
            ->orderByDesc('CREATED_AT')
            ->when(
                !empty($loan_number),
                fn($q) => $q->where('LOAN_NUMBER', $loan_number)
            )
            ->when(
                !empty($atas_nama),
                fn($q) =>
                $q->whereHas('customer', function ($c) use ($atas_nama) {
                    $c->where('NAME', 'LIKE', "%{$atas_nama}%");
                })
            )
            ->when(
                !empty($cabang),
                fn($q) =>
                $q->whereHas('branch', function ($b) use ($cabang) {
                    $b->where('CODE', $cabang);
                })
            )
            ->when(
                $request->filled('dari') && $request->filled('sampai'),
                function ($q) use ($dari, $sampai) {
                    $q->whereBetween('CREATED_AT', [
                        Carbon::parse($dari)->startOfDay(),
                        Carbon::parse($sampai)->endOfDay()
                    ]);
                },
                function ($q) {
                    $q->whereDate('CREATED_AT', now());
                }
            )
            ->get();

        $dto = R_DetailDocument::collection($results);

        return response()->json($dto);
    }
}

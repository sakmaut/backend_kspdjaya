<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\CrCollateralMovingLog\CrCollateralMovingLogRepository;
use App\Http\Resources\R_CreditCancelLog;
use App\Models\M_ApplicationApproval;
use App\Models\M_ApplicationApprovalLog;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationGuarantor;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_CreditCancelLog;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteSertification;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use App\Models\M_CustomerExtra;
use App\Models\M_LocationStatus;
use App\Models\M_SurveyApproval;
use App\Models\M_SurveyApprovalLog;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class Credit extends Controller
{

    private $timeNow;
    protected $locationStatus;

    public function __construct(LocationStatus $locationStatus)
    {
        $this->timeNow = Carbon::now();
        $this->locationStatus = $locationStatus;
    }

    public function index(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = M_CrApplication::where('ORDER_NUMBER', $request->order_number)->first();

            if (!$data) {
                throw new Exception("Order Number Is Not Exist", 404);
            }

            $execute = $this->buildData($request, $data);

            DB::commit();
            return response()->json($execute, 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    function queryKapos($branchID)
    {

        $result = DB::table('users as a')
            ->select(
                'a.fullname',
                'a.position',
                'a.no_ktp',
                'a.alamat',
                'b.address',
                'b.name',
                'b.city'
            )
            ->leftJoin('branch as b', 'b.id', '=', 'a.branch_id')
            ->where('a.position', 'KAPOS')
            ->where('a.status', 'active')
            ->where('b.id', $branchID)
            ->first();

        return $result;
    }

    private function setDate()
    {
        $currentDate = now();
        $date = Carbon::parse($currentDate);

        $day = $date->day;

        if ($day >= 26 && $day <= 31) {
            $newDay = $day - 24;
            $date->addMonthsNoOverflow(2)->day = $newDay;
        } else {
            $date->addMonth();
        }

        return $date->format('Y-m-d');
    }

    private function setDateGenerate()
    {
        $currentDate = now();
        $date = Carbon::parse($currentDate);

        $day = $date->day;

        if ($day >= 26 && $day <= 31) {
            $newDay = $day - 24;
            $date->addMonthsNoOverflow(1)->day = $newDay;
        }

        $setDate = $date->format('Y-m-d');

        return $setDate;
    }

    private function setCreatedAt($date)
    {
        $date = Carbon::parse($date);

        $day = $date->day;

        if ($day >= 26 && $day <= 31) {
            $newDay = $day - 24;
            $date->addMonthsNoOverflow(1)->day = $newDay;
        }

        $setDate = $date->format('Y-m-d');

        return $setDate;
    }

    private function buildData($request, $data)
    {
        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
        $cr_guarantor = M_CrApplicationGuarantor::where('APPLICATION_ID', $data->ID)->get();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID', $data->ID)->first();
        $pihak1 = $this->queryKapos($data->BRANCH);

        $set_tgl_awal = $this->setDate();
        $setDategenerate = $this->setDateGenerate();
        $principal = $data->POKOK_PEMBAYARAN;
        $angsuran = $data->INSTALLMENT;

        $type = $data->INSTALLMENT_TYPE;

        if (strtolower($type) == 'bulanan') {
            $data_credit_schedule = $this->generateAmortizationSchedule($setDategenerate, $data);

            $installment_count = count($data_credit_schedule);
        } else {
            $data_credit_schedule = $this->generateAmortizationScheduleMusiman($setDategenerate, $data);

            $installment_count = count($data_credit_schedule);
        }

        $schedule = [];

        $getCreditData = M_Credit::where('ORDER_NUMBER', $request->order_number);

        $check_exist = $getCreditData->first();
        $check_count = $getCreditData->count();

        if ($check_exist && !empty($check_exist->LOAN_NUMBER)) {
            $credit_schedule = M_CreditSchedule::where('LOAN_NUMBER', $check_exist->LOAN_NUMBER)->get();

            $no = 1;
            foreach ($credit_schedule as $key) {
                $schedule[] = [
                    'angsuran_ke' =>  $no++,
                    'tgl_angsuran' => $key['PAYMENT_DATE'],
                    'pokok' => $key['PRINCIPAL'],
                    'bunga' => $key['INTEREST'],
                    'total_angsuran' => $key['INSTALLMENT'],
                    'baki_debet' => $key['PRINCIPAL_REMAINS']
                ];
            }
        }

        $SET_UUID = Uuid::uuid7()->toString();
        $loan_number = generateCode($request, 'credit', 'LOAN_NUMBER');
        $cust_code = generateCustCode($request, 'customer', 'CUST_CODE');

        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $data->CR_SURVEY_ID)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $array_build = [
            "no_perjanjian" => !$check_exist && $request->flag == 'yes' ? $loan_number ?? null : $check_exist->LOAN_NUMBER ?? null,
            "cabang" => 'CABANG ' . strtoupper($pihak1->name ?? null),
            "kota" => strtoupper($pihak1->city ?? null),
            "alamat_kantor" => strtoupper($pihak1->address ?? null),
            "tgl_cetak" => !empty($check_exist) ? Carbon::parse($check_exist->CREATED_AT)->format('Y-m-d') : null,
            "tgl_awal_angsuran" => !empty($check_exist) ? Carbon::parse($check_exist->INSTALLMENT_DATE)->format('Y-m-d') : Carbon::parse($set_tgl_awal)->format('Y-m-d'),
            "flag" => !$check_exist ? 0 : 1,
            "pihak_1" => [
                "nama" => strtoupper($pihak1->fullname ?? null),
                "jabatan" => strtoupper($pihak1->position ?? null),
                "no_ktp" => strtoupper($pihak1->no_ktp ?? null),
                "alamat" => strtoupper($pihak1->alamat ?? null)
            ],
            "pihak_2" => [
                "nama" => strtoupper($cr_personal->NAME ?? null),
                "no_identitas" => strtoupper($cr_personal->ID_NUMBER ?? null),
                "alamat" => strtoupper($cr_personal->ADDRESS ?? null)
            ],
            "penjamin" => [],
            "pasangan" => [
                "nama_pasangan" => $cr_spouse->NAME ?? null,
                "tmptlahir_pasangan" => $cr_spouse->BIRTHPLACE ?? null,
                "pekerjaan_pasangan" => $cr_spouse->OCCUPATION ?? null,
                "tgllahir_pasangan" => $cr_spouse->BIRTHDATE ?? null,
                "alamat_pasangan" => $cr_spouse->ADDRESS ?? null
            ],
            "pokok_margin" => bilangan($principal) ?? null,
            "tenor" => bilangan($data->TENOR, false) ?? null,
            "credit_id" => !empty($check_exist) ? $check_exist->ID : null,
            "tgl_awal_pk" => !empty($check_exist) ? $this->setCreatedAt($check_exist->CREATED_AT) : parseDatetoYMD($setDategenerate),
            "tgl_akhir_pk" => !empty($check_exist) ? Carbon::parse($check_exist->END_DATE)->format('Y-m-d') : Carbon::parse($setDategenerate)->addMonths(intval($data->TENOR))->format('Y-m-d') ?? null,
            "angsuran" => bilangan($angsuran) ?? null,
            "opt_periode" => $data->OPT_PERIODE ?? null,
            "jaminan" => [],
            "order_validation" => [],
            "struktur" => $check_exist != null && !empty($check_exist->LOAN_NUMBER) ? $schedule : $data_credit_schedule ?? null
        ];

        $ktp = $cr_personal->ID_NUMBER;
        $kk = $cr_personal->KK;

        $checkIdNumber = DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->where('a.STATUS', 'A')
            ->where('b.ID_NUMBER', $ktp)
            ->where('a.ORDER_NUMBER', '!=', $request->order_number)
            ->count();

        $checkKkNumber = DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->where('a.STATUS', 'A')
            ->where('b.KK_NUMBER', $kk)
            ->where('a.ORDER_NUMBER', '!=', $request->order_number)
            ->count();

        if (!isset($array_build["order_validation"])) {
            $array_build["order_validation"] = [];
        }

        // Validate KTP
        if ($checkIdNumber > 2) {
            $array_build["order_validation"][] = "KTP : No KTP {$ktp} Masih Ada yang Aktif";
        }

        // Validate KK
        if ($checkKkNumber > 2) {
            $array_build["order_validation"][] = "KK : No KK {$kk} Aktif Lebih Dari 2";
        }

        foreach ($guarente_vehicle as $list) {

            if ($list->CHASIS_NUMBER == '' && $list->ENGINE_NUMBER == '') {
                $arrayList["order_validation"][] =
                    "Jaminan : Jaminan No Mesin dan No Rangka Tidak Boleh Kosong";
            } else {
                $result = DB::table('cr_collateral as a')
                    ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
                    ->select('b.ORDER_NUMBER', 'a.STATUS', 'b.CREATED_AT')
                    ->where('a.STATUS', '!=', 'RILIS')
                    ->where('a.CHASIS_NUMBER', $list->CHASIS_NUMBER)
                    ->where('a.ENGINE_NUMBER', $list->CHASIS_NUMBER)
                    ->where('b.ORDER_NUMBER', '!=', $request->order_number)
                    ->get();

                if ($result->isNotEmpty()) {
                    $array_build["order_validation"][] = "Jaminan : Jaminan No Mesin {$list->ENGINE_NUMBER} dan No Rangka {$list->CHASIS_NUMBER} Masih Belum DiRilis";
                }
            }
        }

        if ($check_exist) {

            $getCollateral = M_CrCollateral::where('CR_CREDIT_ID', $check_exist->ID)->get();

            foreach ($getCollateral as $list) {
                $array_build['jaminan'][] = [
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
                        "alamat_bpkb" => $list->BPKB_ADDRESS,
                        "no_faktur" => $list->INVOICE_NUMBER,
                        "no_stnk" => $list->STNK_NUMBER,
                        "tgl_stnk" => $list->STNK_VALID_DATE,
                        "nilai" => (int) $list->VALUE
                    ]
                ];
            }
        } else {
            foreach ($guarente_vehicle as $list) {
                $array_build['jaminan'][] = [
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
                        "alamat_bpkb" => $list->BPKB_ADDRESS,
                        "no_faktur" => $list->INVOICE_NUMBER,
                        "no_stnk" => $list->STNK_NUMBER,
                        "tgl_stnk" => $list->STNK_VALID_DATE,
                        "nilai" => (int) $list->VALUE
                    ]
                ];
            }
        }

        foreach ($cr_guarantor as $list) {
            $array_build['penjamin'][] = [
                "id" => $list->ID ?? null,
                "nama" => $list->NAME ?? null,
                "jenis_kelamin" => $list->GENDER ?? null,
                "tempat_lahir" => $list->BIRTHPLACE ?? null,
                "tgl_lahir" => $list->BIRTHDATE ?? null,
                "alamat" => $list->ADDRESS ?? null,
                "tipe_identitas"  => $list->IDENTIY_TYPE ?? null,
                "no_identitas"  => $list->NUMBER_IDENTITY ?? null,
                "pekerjaan"  => $list->OCCUPATION ?? null,
                "lama_bekerja"  => intval($list->WORK_PERIOD ?? null),
                "hub_cust" => $list->STATUS_WITH_DEBITUR ?? null,
                "no_hp" => $list->MOBILE_NUMBER ?? null,
                "pendapatan" => $list->INCOME ?? null,
            ];
        }

        if (!$check_exist && $check_count <= 1 && $request->flag == 'yes') {

            // $checkCountKK = $this->countKK($cr_personal->KK ?? null);

            // if ($checkCountKK > 2) {
            //     throw new Exception("KK greater than 2", 500);
            // }

            $checkCreditMaxLoan = DB::table('credit as a')
                ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->where('a.STATUS', 'A')
                ->where('b.ID_NUMBER', $cr_personal->ID_NUMBER)
                ->count();

            if ($checkCreditMaxLoan > 2) {
                throw new Exception("Loan Number Active Is 2", 500);
            }

            $checkLoanNumberExist = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

            if ($checkLoanNumberExist) {
                throw new Exception("Loan Number Exist", 500);
            }

            $this->insert_credit($SET_UUID, $request, $data, $loan_number, $installment_count, $cust_code);

            $no = 1;
            foreach ($data_credit_schedule as $list) {
                $credit_schedule =
                    [
                        'ID' => Uuid::uuid7()->toString(),
                        'LOAN_NUMBER' => $loan_number,
                        'INSTALLMENT_COUNT' => $no++,
                        'PAYMENT_DATE' => parseDatetoYMD($list['tgl_angsuran']),
                        'PRINCIPAL' => $list['pokok'],
                        'INTEREST' => $list['bunga'],
                        'INSTALLMENT' => $list['total_angsuran'],
                        'PRINCIPAL_REMAINS' => $list['baki_debet']
                    ];

                M_CreditSchedule::create($credit_schedule);
            }

            $this->insert_customer($request, $data, $cust_code);
            $this->insert_customer_xtra($data, $cust_code);
            $this->insert_collateral($request, $data, $SET_UUID, $loan_number);
            $this->insert_collateral_sertification($request, $data, $SET_UUID);
        }

        return $array_build;
    }

    private function insert_credit($SET_UUID, $request, $data, $loan_number, $installment_count, $cust_code)
    {
        $survey = M_CrSurvey::find($data->CR_SURVEY_ID);

        $setDate = $this->setDate();
        $setDategenerate = $this->setDateGenerate();

        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();

        $data_credit = [
            'ID' =>  $SET_UUID,
            'LOAN_NUMBER' => $loan_number,
            'STATUS_REC' => 'AC',
            'BRANCH'   => $data->BRANCH,
            'ORDER_NUMBER' => $data->ORDER_NUMBER,
            'STATUS'  => 'A',
            'MCF_ID'  => $survey->created_by ?? null,
            'ENTRY_DATE'  => $setDategenerate ?? null,
            'END_DATE'  => Carbon::parse($setDategenerate)->addMonths(intval($data->TENOR))->format('Y-m-d') ?? null,
            'FIRST_ARR_DATE'  => null,
            'INSTALLMENT_DATE'  => $setDate ?? null,
            'PCPL_ORI'  => $data->SUBMISSION_VALUE + ($data->TOTAL_ADMIN ?? 0) ?? null,
            'INTRST_ORI' => $data->TOTAL_INTEREST ?? 0,
            'PAID_PRINCIPAL'  => 0,
            'PAID_INTEREST'  => 0,
            'PAID_PENALTY'  => 0,
            'DUE_PRINCIPAL'  => 0,
            'DUE_INTEREST'  => 0,
            'DUE_PENALTY'  => 0,
            'CREDIT_TYPE'  => $data->INSTALLMENT_TYPE ?? null,
            'INSTALLMENT_COUNT'  => $installment_count,
            'PERIOD'  => $data->TENOR,
            'INSTALLMENT'  => $data->INSTALLMENT,
            'FLAT_RATE'  => $data->FLAT_RATE ?? null,
            'EFF_RATE'  => $data->EFF_RATE ?? null,
            'TOTAL_ADMIN' =>  $data->TOTAL_ADMIN ?? 0,
            'VERSION'  => 1,
            'CREATED_BY' => $request->user()->id,
            'CREATED_AT' => Carbon::now(),
        ];

        if (!$check_customer_ktp) {
            $data_credit['CUST_CODE'] = $cust_code;
        } else {
            $data_credit['CUST_CODE'] = $check_customer_ktp->CUST_CODE;
        }

        $existingLoanNumbers = [];

        $existingLoanNumbers[] = $data_credit['LOAN_NUMBER'];

        if (count($existingLoanNumbers) !== count(array_unique($existingLoanNumbers))) {
            return response()->json(['error' => 'Duplicate LOAN_NUMBER in the array. Record not inserted.'], 409);
        }

        $credit = M_Credit::create($data_credit);
        $last_id = $credit->id;

        return $last_id;
    }

    private function insert_customer($request, $data, $cust_code)
    {

        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID', $data->ID)->first();
        $check_customer_ktp =  M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->orderBy('CREATE_DATE', 'desc')->first();

        $getAttachment = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ('ktp', 'kk', 'ktp_pasangan')
                        AND CR_SURVEY_ID = '$data->CR_SURVEY_ID'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        $data_customer = [
            'NAME' => $cr_personal->NAME,
            'ALIAS' => $cr_personal->ALIAS,
            'GENDER' => $cr_personal->GENDER,
            'BIRTHPLACE' => $cr_personal->BIRTHPLACE,
            'BIRTHDATE' => $cr_personal->BIRTHDATE,
            'BLOOD_TYPE' => $cr_personal->BLOOD_TYPE,
            'MOTHER_NAME' => $cr_order->MOTHER_NAME,
            'NPWP' => $cr_order->NO_NPWP,
            'MARTIAL_STATUS' => $cr_personal->MARTIAL_STATUS,
            'MARTIAL_DATE' => $cr_personal->MARTIAL_DATE,
            'ID_TYPE' => $cr_personal->ID_TYPE,
            'ID_NUMBER' => $cr_personal->ID_NUMBER,
            'KK_NUMBER' => $cr_personal->KK,
            'ID_ISSUE_DATE' => $cr_personal->ID_ISSUE_DATE,
            'ID_VALID_DATE' => $cr_personal->ID_VALID_DATE,
            'ADDRESS' => $cr_personal->ADDRESS,
            'RT' => $cr_personal->RT,
            'RW' => $cr_personal->RW,
            'PROVINCE' => $cr_personal->PROVINCE,
            'CITY' => $cr_personal->CITY,
            'KELURAHAN' => $cr_personal->KELURAHAN,
            'KECAMATAN' => $cr_personal->KECAMATAN,
            'ZIP_CODE' => $cr_personal->ZIP_CODE,
            'KK' => $cr_personal->KK,
            'CITIZEN' => $cr_personal->CITIZEN,
            'INS_ADDRESS' => $cr_personal->INS_ADDRESS,
            'INS_RT' => $cr_personal->INS_RT,
            'INS_RW' => $cr_personal->INS_RW,
            'INS_PROVINCE' => $cr_personal->INS_PROVINCE,
            'INS_CITY' => $cr_personal->INS_CITY,
            'INS_KELURAHAN' => $cr_personal->INS_KELURAHAN,
            'INS_KECAMATAN' => $cr_personal->INS_KECAMATAN,
            'INS_ZIP_CODE' => $cr_personal->INS_ZIP_CODE,
            'OCCUPATION' => $cr_personal->OCCUPATION,
            'OCCUPATION_ON_ID' => $cr_personal->OCCUPATION_ON_ID,
            'INCOME' => $cr_order->INCOME_PERSONAL,
            'RELIGION' => $cr_personal->RELIGION,
            'EDUCATION' => $cr_personal->EDUCATION,
            'PROPERTY_STATUS' => $cr_personal->PROPERTY_STATUS,
            'PHONE_HOUSE' => $cr_personal->PHONE_HOUSE,
            'PHONE_PERSONAL' => $cr_personal->PHONE_PERSONAL,
            'PHONE_OFFICE' => $cr_personal->PHONE_OFFICE,
            'EXT_1' => $cr_personal->EXT_1,
            'EXT_2' => $cr_personal->EXT_2,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now(),
            'CREATE_USER' => $request->user()->id,
        ];

        if (!$check_customer_ktp) {
            $data_customer['ID'] = Uuid::uuid7()->toString();
            $data_customer['CUST_CODE'] = $cust_code;
            $last_id = M_Customer::create($data_customer);

            $this->createCustomerDocuments($last_id->ID, $getAttachment);
        } else {
            $check_customer_ktp->update($data_customer);

            $this->createCustomerDocuments($check_customer_ktp->ID, $getAttachment);
        }
    }

    private function createCustomerDocuments($customerId, $attachments)
    {

        M_CustomerDocument::where('CUSTOMER_ID', $customerId)->delete();

        foreach ($attachments as $res) {
            $custmer_doc_data = [
                'CUSTOMER_ID' => $customerId,
                'TYPE' => $res->TYPE,
                'COUNTER_ID' => $res->COUNTER_ID,
                'PATH' => $res->PATH,
                'TIMESTAMP' => round(microtime(true) * 1000)
            ];

            M_CustomerDocument::create($custmer_doc_data);
        }
    }

    private function insert_customer_xtra($data, $cust_code)
    {

        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
        $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID', $data->ID)->first();
        $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID', $data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID', $data->ID)->first();
        $update = M_CustomerExtra::where('CUST_CODE', $check_customer_ktp->CUST_CODE)->first();


        $data_customer_xtra = [
            'OTHER_OCCUPATION_1' => $cr_personal_extra->OTHER_OCCUPATION_1 ?? null,
            'OTHER_OCCUPATION_2' => $cr_personal_extra->OTHER_OCCUPATION_2 ?? null,
            'SPOUSE_NAME' =>  $cr_spouse->NAME ?? null,
            'SPOUSE_BIRTHPLACE' =>  $cr_spouse->BIRTHPLACE ?? null,
            'SPOUSE_BIRTHDATE' =>  $cr_spouse->BIRTHDATE ?? null,
            'SPOUSE_ID_NUMBER' => $cr_spouse->NUMBER_IDENTITY ?? null,
            'SPOUSE_INCOME' => $cr_order->INCOME_SPOUSE ?? null,
            'SPOUSE_ADDRESS' => $cr_spouse->ADDRESS ?? null,
            'SPOUSE_OCCUPATION' => $cr_spouse->OCCUPATION ?? null,
            'SPOUSE_RT' => null,
            'SPOUSE_RW' => null,
            'SPOUSE_PROVINCE' => null,
            'SPOUSE_CITY' => null,
            'SPOUSE_KELURAHAN' => null,
            'SPOUSE_KECAMATAN' => null,
            'SPOUSE_ZIP_CODE' => null,
            'INS_ADDRESS' => null,
            'INS_RT' => null,
            'INS_RW' => null,
            'INS_PROVINCE' => null,
            'INS_CITY' => null,
            'INS_KELURAHAN' => null,
            'INS_KECAMATAN' => null,
            'INS_ZIP_CODE' => null,
            'EMERGENCY_NAME' => $cr_personal_extra->EMERGENCY_NAME ?? null,
            'EMERGENCY_ADDRESS' => $cr_personal_extra->EMERGENCY_ADDRESS ?? null,
            'EMERGENCY_RT' => $cr_personal_extra->EMERGENCY_RT ?? null,
            'EMERGENCY_RW' => $cr_personal_extra->EMERGENCY_RW ?? null,
            'EMERGENCY_PROVINCE' => $cr_personal_extra->EMERGENCY_PROVINCE ?? null,
            'EMERGENCYL_CITY' => $cr_personal_extra->EMERGENCY_CITY ?? null,
            'EMERGENCY_KELURAHAN' => $cr_personal_extra->EMERGENCY_KELURAHAN ?? null,
            'EMERGENCYL_KECAMATAN' => $cr_personal_extra->EMERGENCY_KECAMATAN ?? null,
            'EMERGENCY_ZIP_CODE' => $cr_personal_extra->EMERGENCY_ZIP_CODE ?? null,
            'EMERGENCY_PHONE_HOUSE' => $cr_personal_extra->EMERGENCY_PHONE_HOUSE ?? null,
            'EMERGENCY_PHONE_PERSONAL' => $cr_personal_extra->EMERGENCY_PHONE_PERSONAL ?? null
        ];

        if (!$update) {
            $data_customer_xtra['ID'] = Uuid::uuid7()->toString();
            $data_customer_xtra['CUST_CODE'] =  $cust_code;
            M_CustomerExtra::create($data_customer_xtra);
        } else {
            $update->update($data_customer_xtra);
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

    private function insert_collateral($request, $data, $lastID, $loan_number)
    {
        $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $data->CR_SURVEY_ID)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $doc = $this->attachment_guarante($data->CR_SURVEY_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'");

        $setHeaderID = '';
        foreach ($doc as $res) {
            $setHeaderID = $res->COUNTER_ID ?? '';
        }

        if ($data_collateral->isNotEmpty()) {
            foreach ($data_collateral as $res) {
                $data_jaminan = [
                    'HEADER_ID' => $setHeaderID,
                    'CR_CREDIT_ID' => $lastID ?? null,
                    'VEHICLE_TYPE' => $res->VEHICLE_TYPE ?? '',
                    'TYPE' => $res->TYPE ?? null,
                    'BRAND' => $res->BRAND ?? null,
                    'PRODUCTION_YEAR' => $res->PRODUCTION_YEAR ?? null,
                    'COLOR' => $res->COLOR ?? null,
                    'ON_BEHALF' => $res->ON_BEHALF ?? null,
                    'POLICE_NUMBER' => isset($res->POLICE_NUMBER) ? preg_replace('/[^a-zA-Z0-9]/', '', $res->POLICE_NUMBER) : null,
                    'CHASIS_NUMBER' => $res->CHASIS_NUMBER ?? null,
                    'ENGINE_NUMBER' => $res->ENGINE_NUMBER ?? null,
                    'BPKB_NUMBER' => isset($res->BPKB_NUMBER) ? preg_replace('/[^a-zA-Z0-9]/', '', $res->BPKB_NUMBER) : null,
                    'BPKB_ADDRESS' => $res->BPKB_ADDRESS ?? null,
                    'STNK_NUMBER' => $res->STNK_NUMBER ?? null,
                    'INVOICE_NUMBER' => $res->INVOICE_NUMBER ?? null,
                    'STNK_VALID_DATE' => $res->STNK_VALID_DATE ?? null,
                    'VALUE' => $res->VALUE ?? null,
                    'LOCATION_BRANCH' => $data->BRANCH,
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'STATUS' => 'NORMAL',
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];

                $execute = M_CrCollateral::create($data_jaminan);

                $statusLog = 'NEW ' . $loan_number ?? '';
                $this->locationStatus->createLocationStatusLog($execute->ID, $data->BRANCH, $statusLog);

                foreach ($doc as $res) {
                    $custmer_doc_data = [
                        'COLLATERAL_ID' => $execute->ID,
                        'TYPE' => $res->TYPE,
                        'COUNTER_ID' => $res->COUNTER_ID,
                        'PATH' => $res->PATH
                    ];

                    M_CrCollateralDocument::create($custmer_doc_data);
                }
            }
        }
    }

    private function insert_collateral_sertification($request, $data, $lastID)
    {
        $data_collateral = M_CrGuaranteSertification::where('CR_SURVEY_ID', $data->CR_SURVEY_ID)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $doc = $this->attachment_guarante($data->CR_SURVEY_ID, "'sertifikat'");

        if ($data_collateral->isNotEmpty()) {
            foreach ($data_collateral as $res) {
                $data_jaminan = [
                    'HEADER_ID' => "",
                    'CR_CREDIT_ID' => $lastID ?? null,
                    'STATUS_JAMINAN' => $res->STATUS_JAMINAN,
                    'NO_SERTIFIKAT' => $res->NO_SERTIFIKAT,
                    'STATUS_KEPEMILIKAN' => $res->STATUS_KEPEMILIKAN,
                    'IMB' => $res->IMB,
                    'LUAS_TANAH' => $res->LUAS_TANAH,
                    'LUAS_BANGUNAN' => $res->LUAS_BANGUNAN,
                    'LOKASI' => $res->LOKASI,
                    'PROVINSI' => $res->PROVINSI,
                    'KAB_KOTA' => $res->KAB_KOTA,
                    'KECAMATAN' => $res->KECAMATAN,
                    'DESA' => $res->DESA,
                    'ATAS_NAMA' => $res->ATAS_NAMA,
                    'NILAI' => $res->NILAI,
                    'LOCATION' => $data->BRANCH,
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => $request->user()->id,
                ];

                $execute =  M_CrCollateralSertification::create($data_jaminan);

                $log = [
                    'COLLATERAL_ID' => $execute->id,
                    'TYPE' => 'sertifikat',
                    'STATUS' => 'NORMAL',
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'CREATED_BY' => $request->user()->id,
                    'CREATED_AT' => $this->timeNow
                ];

                M_LocationStatus::create($log);

                foreach ($doc as $res) {
                    $custmer_doc_data = [
                        'COLLATERAL_ID' => $execute->ID,
                        'TYPE' => $res->TYPE,
                        'COUNTER_ID' => $res->COUNTER_ID,
                        'PATH' => $res->PATH
                    ];

                    M_CrCollateralDocument::create($custmer_doc_data);
                }
            }
        }
    }

    public function checkCollateral(Request $request)
    {
        try {

            if (collect($request->jaminan)->isNotEmpty()) {
                foreach ($request->jaminan as $result) {
                    $checkMethod = $this->checkCollateralExists($result['type'], $result['number']);

                    if ($checkMethod->isNotEmpty()) {
                        return response()->json(['status' => false, 'message' => "ADA JAMINAN YANG AKTIF BANGSAT", "data" => $checkMethod->first()->LOAN_NUMBER], 400);
                    }
                }
            }

            return response()->json(['status' => true, 'message' => "Aman Cuk"], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function checkCollateralExists($type, $no)
    {
        $table = $type === 'kendaraan' ? 'cr_collateral' : 'cr_collateral_sertification';
        $column = $type === 'kendaraan' ? 'BPKB_NUMBER' : 'NO_SERTIFIKAT';

        return DB::table('credit as a')
            ->leftJoin($table . ' as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
            ->where('b.' . $column, $no)
            ->where('a.STATUS', 'A')
            ->select('a.LOAN_NUMBER')
            ->get();
    }

    private function generateAmortizationSchedule($setDate, $data)
    {
        $schedule = [];
        $remainingBalance = $data->POKOK_PEMBAYARAN;
        $term = ceil($data->TENOR);
        $angsuran = $data->INSTALLMENT;
        $suku_bunga_konversi = ($data->FLAT_RATE / 100);
        $ttal_bunga = $data->TOTAL_INTEREST;
        $totalInterestPaid = 0;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($remainingBalance * $suku_bunga_konversi, 2);

            if ($i < $term) {
                $principalPayment = round($angsuran - $interest, 2);
            } else {
                $principalPayment = round($remainingBalance, 2);
                $interest = round($ttal_bunga - $totalInterestPaid, 2);
            }

            $totalPayment = round($principalPayment + $interest, 2);
            $remainingBalance = round($remainingBalance - $principalPayment, 2);
            $totalInterestPaid += $interest;
            if ($i == $term) {
                $remainingBalance = 0.00;
            }

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => setPaymentDate($setDate, $i),
                'baki_debet_awal' => floatval($remainingBalance + $principalPayment),
                'pokok' => floatval($principalPayment),
                'bunga' => floatval($interest),
                'total_angsuran' => floatval($totalPayment),
                'baki_debet' => floatval($remainingBalance)
            ];
        }

        return $schedule;
    }

    private function generateAmortizationScheduleMusiman($setDate, $data)
    {
        $schedule = [];
        $remainingBalance = $data->POKOK_PEMBAYARAN;  // Initial loan amount (POKOK_PEMBAYARAN)
        $term = ceil($data->TENOR);  // Loan term in months (TENOR)
        $angsuran = $data->INSTALLMENT;  // Monthly installment (INSTALLMENT)
        $suku_bunga_konversi = round($data->FLAT_RATE / 100, 10);  // Monthly interest rate (FLAT_RATE divided by 100)
        $ttal_bunga = $data->TOTAL_INTEREST;  // Total interest (TOTAL_INTEREST)
        $totalInterestPaid = 0;  // Total interest paid so far

        $tenorList = [
            '3' => 1,
            '6' => 1,
            '12' => 2,
            '18' => 3
        ];

        $term = $tenorList[$term] ?? 0;

        $monthsToAdd = ($data->TENOR / $tenorList[$data->TENOR]) ?? 0;

        $startDate = new DateTime($setDate);

        for ($i = 1; $i <= $term; $i++) {

            $interest = round($remainingBalance * $suku_bunga_konversi, 2);

            if ($i < $term) {
                $principalPayment = round($angsuran - $interest, 2);
            } else {
                $principalPayment = round($remainingBalance, 2);
                $interest = round($ttal_bunga - $totalInterestPaid, 2);
            }

            $totalPayment = round($principalPayment + $interest, 2);
            $remainingBalance = round($remainingBalance - $principalPayment, 2);
            $totalInterestPaid += $interest;

            if ($i == $term) {
                $remainingBalance = 0.00;
            }

            $paymentDate = clone $startDate;

            $paymentDate->modify("+{$monthsToAdd} months");

            // Format the date as required (e.g., 'Y-m-d')
            $formattedPaymentDate = $paymentDate->format('Y-m-d');

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => $formattedPaymentDate,
                'pokok' => floatval($principalPayment),
                'bunga' => floatval($interest),
                'total_angsuran' => floatval($totalPayment),
                'baki_debet' => floatval($remainingBalance)
            ];

            $startDate = $paymentDate;
        }

        return $schedule;
    }

    public function pkCancel(Request $request)
    {
        try {
            $request->validate([
                'order_number' => 'required|string',
                'req_flag' => 'required|string',
            ]);

            $orderNumber = $request->order_number;
            $reqFlag = $request->req_flag;

            // Handle 'revisi' request
            if ($reqFlag === 'revisi') {
                return $this->handleRevisi($request, $orderNumber);
            }

            // Handle 'cancel' request
            if ($reqFlag === 'cancel') {
                return $this->handleCancel($request, $orderNumber);
            }

            throw new Exception("Request Flag Not Valid", 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function handleRevisi(Request $request, string $orderNumber)
    {
        $check = M_Credit::where('ORDER_NUMBER', $orderNumber)->first();

        if ($check) {
            throw new Exception("Order Number Has Created Order, You Must Cancelled", 404);
        }

        $checkOrderNumber = M_CrApplication::where('ORDER_NUMBER', $orderNumber)->first();
        if (!$checkOrderNumber) {
            throw new Exception("Order Number {$orderNumber} No Exist", 404);
        }

        $this->updateApplicationApproval($request, $checkOrderNumber, 'REORADM', 'revisi admin');
        $this->updateSurveyApproval($request, $checkOrderNumber, 'REORADM', 'revisi admin');

        return response()->json(['message' => "Success Revisi Order"], 200);
    }

    private function handleCancel(Request $request, string $orderNumber)
    {
        $check = M_Credit::where([
            'ORDER_NUMBER' => $orderNumber,
            'STATUS' => 'A'
        ])->whereNull('DELETED_BY')
            ->whereNull('DELETED_AT')
            ->first();

        if (!$check) {
            throw new Exception("Order Number Not Exist", 404);
        }

        M_CreditCancelLog::create([
            'CREDIT_ID' => $orderNumber ?? '',
            'REQUEST_FLAG' => "CANCEL",
            'REQUEST_BY' => $request->user()->id ?? '',
            'REQUEST_BRANCH' => $request->user()->branch_id ?? '',
            'REQUEST_DATE' => Carbon::now(),
            'REQUEST_DESCR' => $request->descr ?? '',
        ]);

        $checkSurveyId = M_CrApplication::where('ORDER_NUMBER', $check->ORDER_NUMBER)->first();
        if ($checkSurveyId) {
            $this->updateApplicationApproval($request, $checkSurveyId, 'REQCANCELHO', 'menunggu cancel order');
            $this->updateSurveyApproval($request, $checkSurveyId, 'REQCANCELHO', 'menunggu cancel order');
        }

        if (strtolower($request->user()->position) === 'ho' && isset($request->flag) && !empty($request->flag)) {
            return $this->processHoApproval($request, $check);
        }
    }

    private function processHoApproval(Request $request, $check)
    {
        if (strtolower($request->flag) === 'yes') {

            $check->update([
                'STATUS' => 'C',
                'DELETED_BY' => $request->user()->id,
                'DELETED_AT' => Carbon::now(),
            ]);

            $checkCreditCancel = M_CreditCancelLog::where('CREDIT_ID', $check->ORDER_NUMBER)->first();

            if ($checkCreditCancel) {
                $checkCreditCancel->update([
                    'ONCHARGE_DESCR' => $request->descr_ho ?? '',
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_FLAG' => $request->flag,
                ]);
            }

            $updateProsessRequest = M_CrApplication::where('ORDER_NUMBER', $check->ORDER_NUMBER)->first();
            if ($updateProsessRequest) {
                $this->updateApplicationApproval($request, $updateProsessRequest, 'CANCELHO', 'cancel order');
                $this->updateSurveyApproval($request, $updateProsessRequest, 'CANCELHO', 'cancel order');
            }
        } elseif (strtolower($request->flag) === 'no') {
            $application = M_CrApplication::where('ORDER_NUMBER', $check->ORDER_NUMBER)->first();

            $approval = M_ApplicationApproval::where('cr_application_id', $application->ID)->first();
            if ($approval) {
                $approval->update([
                    'code' => 'APHO',
                    'cr_prospect_id' => $application->CR_SURVEY_ID ?? null,
                    'cr_application_id' => $application->ID ?? null,
                    'application_result' => 'disetujui ho',
                    'cr_application_ho' => $request->user()->id,
                    'cr_application_ho_time' => Carbon::now()->format('Y-m-d'),
                    'cr_application_ho_desc' => $request->descr_ho ?? '',
                ]);

                M_ApplicationApprovalLog::create([
                    'CODE' => 'CANCELREQADM',
                    'POSITION' => $request->user()->position ?? null,
                    'APPLICATION_APPROVAL_ID' => $application->ID ?? null,
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'APPROVAL_RESULT' => 'request order cancel ditolak ho',
                ]);
            }

            $surveyApproval = M_SurveyApproval::where('CR_SURVEY_ID', $application->CR_SURVEY_ID)->first();
            if ($surveyApproval) {
                $surveyApproval->update([
                    'CODE' => 'APHO',
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_DESCR' => $request->descr_ho ?? '',
                    'ONCHARGE_TIME' => Carbon::now(),
                    'APPROVAL_RESULT' => 'disetujui ho',
                ]);

                M_SurveyApprovalLog::create([
                    'CODE' => 'CANCELREQADM',
                    'SURVEY_APPROVAL_ID' => $application->CR_SURVEY_ID,
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'APPROVAL_RESULT' => 'request order cancel ditolak ho',
                ]);
            }
        }

        return response()->json(['message' => "Success Cancel Order"], 200);
    }

    private function updateApplicationApproval(Request $request, $application, $code, $descr)
    {
        $approval = M_ApplicationApproval::where('cr_application_id', $application->ID)->first();
        if ($approval) {
            $approval->update([
                'code' => $code,
                'cr_prospect_id' => $application->CR_SURVEY_ID ?? null,
                'cr_application_id' => $application->ID ?? null,
                'application_result' => $descr,
                'cr_application_ho' => $request->user()->id,
                'cr_application_ho_time' => Carbon::now()->format('Y-m-d'),
                'cr_application_ho_desc' => $request->descr_ho ?? '',
            ]);

            M_ApplicationApprovalLog::create([
                'CODE' => $code,
                'POSITION' => $request->user()->position ?? null,
                'APPLICATION_APPROVAL_ID' => $application->ID ?? null,
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'APPROVAL_RESULT' => $descr,
            ]);
        }
    }

    private function updateSurveyApproval(Request $request, $application, $code, $descr)
    {
        $surveyApproval = M_SurveyApproval::where('CR_SURVEY_ID', $application->CR_SURVEY_ID)->first();
        if ($surveyApproval) {
            $surveyApproval->update([
                'CODE' => $code,
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_DESCR' => $request->descr_ho ?? '',
                'ONCHARGE_TIME' => Carbon::now(),
                'APPROVAL_RESULT' => $descr,
            ]);

            M_SurveyApprovalLog::create([
                'CODE' => $code,
                'SURVEY_APPROVAL_ID' => $application->CR_SURVEY_ID,
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'APPROVAL_RESULT' => $descr,
            ]);
        }
    }

    public function pkCancelList(Request $request)
    {
        try {
            $data = M_CreditCancelLog::where(function ($query) {
                $query->whereNull('ONCHARGE_PERSON')
                    ->orWhere('ONCHARGE_PERSON', '');
            })
                ->where(function ($query) {
                    $query->whereNull('ONCHARGE_TIME')
                        ->orWhere('ONCHARGE_TIME', '');
                })
                ->get();

            $dto = R_CreditCancelLog::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

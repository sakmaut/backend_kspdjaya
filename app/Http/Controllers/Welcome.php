<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\LocationStatus;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\StatusApproval;
use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrProspect;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use App\Models\M_CustomerExtra;
use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_FirstArr;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LocationStatus;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Models\M_TelegramBotSend;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

class Welcome extends Controller
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
        // $no_inv = $request->no_fasilitas;
        // $setDAte = '2025-02-03 16:19:27';

        // if (isset($request->struktur) && is_array($request->struktur)) {
        //     foreach ($request->struktur as $res) {

        //         M_KwitansiStructurDetail::firstOrCreate([
        //             'no_invoice' => $no_inv,
        //             'key' => $res['key'] ?? '',
        //             'loan_number' => $res['loan_number'] ?? ''
        //         ], [
        //             'angsuran_ke' => $res['angsuran_ke'] ?? '',
        //             'tgl_angsuran' => $res['tgl_angsuran'] ?? '',
        //             'principal' => $res['principal'] ?? '',
        //             'interest' => $res['interest'] ?? '',
        //             'installment' => $res['installment'] ?? '',
        //             'principal_remains' => $res['principal_remains'] ?? '',
        //             'payment' => $res['payment'] ?? '',
        //             'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
        //             'bayar_denda' => $res['bayar_denda'] ?? '',
        //             'total_bayar' => $res['total_bayar'] ?? '',
        //             'flag' => $res['flag'] ?? '',
        //             'denda' => $res['denda'] ?? '',
        //             'diskon_denda' => strtolower($request->bayar_dengan_diskon) == 'ya' ? 1 : 0
        //         ]);

        //         $loan_number = $res['loan_number'];
        //         $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        //         $uid = Uuid::uuid7()->toString();

        //         $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid);

        //         M_Payment::create([
        //             'ID' => $uid,
        //             'ACC_KEY' => 'angsuran',
        //             'STTS_RCRD' => 'PAID',
        //             'INVOICE' => $no_inv,
        //             'NO_TRX' => $request->uid,
        //             'PAYMENT_METHOD' => $request->payment_method,
        //             'BRANCH' => 'ANJ',
        //             'LOAN_NUM' => $loan_number,
        //             'VALUE_DATE' => null,
        //             'ENTRY_DATE' => $setDAte,
        //             'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
        //             'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
        //             'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
        //             'OS_AMOUNT' => $os_amount ?? 0,
        //             'START_DATE' => $tgl_angsuran,
        //             'END_DATE' => $setDAte,
        //             'USER_ID' => '39',
        //             'AUTH_BY' => '',
        //             'AUTH_DATE' => $setDAte,
        //             'ARREARS_ID' => $res['id_arrear'] ?? '',
        //             'BANK_NAME' => round(microtime(true) * 1000)
        //         ]);
        //     }
        // }

        return response()->json('ok');

        die;

        $data = M_CrApplication::where('ORDER_NUMBER', $request->order_number)->first();

        $set_tgl_awal = $request->tgl_awal;

        $type = $data->INSTALLMENT_TYPE;

        // if (strtolower($type) == 'bulanan') {
        //     $data_credit_schedule = $this->generateAmortizationSchedule($set_tgl_awal, $data);
        // } else {
        //     $data_credit_schedule = $this->generateAmortizationScheduleMusiman($set_tgl_awal, $data);
        // }

        // $no = 1;
        // foreach ($data_credit_schedule as $list) {
        //     $credit_schedule =
        //         [
        //             'ID' => Uuid::uuid7()->toString(),
        //             'LOAN_NUMBER' => $request->loan_number ?? '',
        //             'INSTALLMENT_COUNT' => $no++,
        //             'PAYMENT_DATE' => parseDatetoYMD($list['tgl_angsuran']),
        //             'PRINCIPAL' => $list['pokok'],
        //             'INTEREST' => $list['bunga'],
        //             'INSTALLMENT' => $list['total_angsuran'],
        //             'PRINCIPAL_REMAINS' => $list['baki_debet']
        //         ];

        //     M_CreditSchedule::create($credit_schedule);
        // }

        $check_exist = M_Credit::where('ORDER_NUMBER', $request->order_number)->first();
        if ($check_exist) {
            $SET_UUID = $check_exist->ID;
            $cust_code = $check_exist->CUST_CODE;

            // $this->insert_customer($request, $data, $cust_code);
            $this->insert_customer_xtra($data, $cust_code);
            $this->insert_collateral($request, $data, $SET_UUID, $request->loan_number);
        }

        return response()->json("MUACHHHHHHHHHHHHHH");
        die;

        $groupedData = [];

        // function telBot()
        // {
        //     $newMessages = M_TelegramBotSend::where('status', 'new')->get();

        //     $datas = [];
        //     foreach ($newMessages as $message) {
        //         $getJson = $message->messages;
        //         if (!empty($getJson)) {
        //             $convert = json_decode($getJson, true);
        //             $findBRanch = M_Branch::find($convert['BRANCH_CODE']);

        //             // Wrap ternary operator in parentheses for correct evaluation
        //             $buildMsg = " MINTA APPROVAL PEMBAYARAN" . "\n" .
        //                 'Tipe = ' . $convert['PAYMENT_TYPE'] . "\n" .
        //                 'Status = Pending' . "\n" .
        //                 'No Transaksi = ' . $convert['NO_TRANSAKSI'] . "\n" .
        //                 'No Kontrak = ' . $convert['LOAN_NUMBER'] . "\n" .
        //                 'Cabang = ' . ($findBRanch ? $findBRanch->NAME : '') . "\n" .
        //                 'Tgl Transaksi = ' . $convert['TGL_TRANSAKSI'];

        //             $response = TelegramBotConfig::sendMessage($buildMsg);

        //             if ($response) {
        //                 $newMessages->update(['status' => 'send']);
        //             }
        //         }
        //     }
        // }

        foreach ($request->all() as $item) {
            // Use no_invoice and angsuran_ke as the key for grouping
            $key = $item['no_invoice'] . '-' . $item['angsuran_ke'];

            // If the group doesn't exist yet, initialize it
            if (!isset($groupedData[$key])) {
                // Initialize the group for this invoice and angsuran_ke
                $groupedData[$key] = [
                    "type" => $item['PAYMENT_TYPE'],
                    "status" => $item['STTS_PAYMENT'],
                    "method" => $item['METODE_PEMBAYARAN'],
                    "time" => $item['CREATED_AT'],
                    "by" => $item['CREATED_BY'],
                    "branch" => $item['BRANCH_CODE'],
                    "loan" => $item['LOAN_NUMBER'],
                    "invoice" => $item['no_invoice'],  // keep original no_invoice
                    "tgl_angsuran" => $item['tgl_angsuran'],  // assuming we want to keep the latest tgl_angsuran for each LOAN_NUMBER
                    "angsuran_ke" => $item['angsuran_ke'],
                    "installment" => $item['installment'],
                    "diskon_denda" => $item['diskon_denda'],
                    "flag" => $item['flag'],
                    'details' => [],
                ];
            }

            // Prepare the detail entry based on bayar_denda
            if ($item['bayar_denda'] != 0) {
                // Add both bayar_angsuran and bayar_denda if bayar_denda is not 0
                $detail = [
                    'bayar_angsuran' => $item['bayar_angsuran'],
                    'bayar_denda' => $item['bayar_denda'],
                ];
            } else {
                // If bayar_denda is 0, only add bayar_angsuran
                $detail = [
                    'bayar_angsuran' => $item['bayar_angsuran'],
                ];
            }

            // Add the detail to the group's details
            $groupedData[$key]['details'][] = $detail;
        }

        foreach ($groupedData as $data) {
            // $uid = Uuid::uuid7()->toString();

            // M_Payment::create([
            //     'ID' => $uid,
            //     'ACC_KEY' => $data['flag'] == 'PAID' ? 'angsuran_denda' : $data['type'] ?? '',
            //     'STTS_RCRD' => 'PAID',
            //     'INVOICE' => $data['invoice'],
            //     'NO_TRX' => $request->uid,
            //     'PAYMENT_METHOD' => $data['method'],
            //     'BRANCH' => $data["branch"],
            //     'LOAN_NUM' => $data['loan'],
            //     'VALUE_DATE' => null,
            //     'ENTRY_DATE' => $data["time"],
            //     'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            //     'TITLE' => 'Angsuran Ke-' . $data['angsuran_ke'],
            //     'ORIGINAL_AMOUNT' => $data['installment'],
            //     'OS_AMOUNT' => $os_amount ?? 0,
            //     'START_DATE' => date('Y-m-d', strtotime($data['tgl_angsuran'])),
            //     'END_DATE' => $data["time"],
            //     'USER_ID' => $data["by"],
            //     'AUTH_BY' => $data["by"],
            //     'AUTH_DATE' => $data["time"],
            //     'ARREARS_ID' => $data['id_arrear'] ?? '',
            //     'BANK_NAME' => round(microtime(true) * 1000)
            // ]);

            $get = M_Payment::where(['LOAN_NUM' => $data["loan"], 'INVOICE' => $data["invoice"], 'TITLE' => 'Angsuran Ke-' . $data['angsuran_ke']])->first();

            $checkDetail = M_PaymentDetail::where(['PAYMENT_ID' => $get->ID])->first();

            if (!$checkDetail) {
                $this->updateCreditSchedule($data['loan'], $data['tgl_angsuran'], $data, $get->ID ?? 0);
            }
        }


        return response()->json('ok', 200);
    }


    function updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => date('Y-m-d', strtotime($tgl_angsuran))
        ])->first();

        if ($credit_schedule) {
            $byr_angsuran = $res['details'][0]['bayar_angsuran'] ?? $res['bayar_angsuran'];

            $valBeforePrincipal = $credit_schedule ? $credit_schedule->PAYMENT_VALUE_PRINCIPAL : 0;
            $valBeforeInterest = $credit_schedule ? $credit_schedule->PAYMENT_VALUE_INTEREST : 0;
            $getPrincipal = $credit_schedule ? $credit_schedule->PRINCIPAL : 0;
            $getInterest = $credit_schedule ? $credit_schedule->INTEREST : 0;

            if ($credit_schedule->PAID_FLAG == 'PAID') {
                $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $getPrincipal);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $getPrincipal]);

                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $getInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $getInterest]);
            } else {
                $new_payment_value_principal = $valBeforePrincipal;
                $new_payment_value_interest = $valBeforeInterest;

                // Process principal payment if needed
                if ($valBeforePrincipal < $getPrincipal) {
                    $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                    if ($byr_angsuran >= $remaining_to_principal) {
                        $new_payment_value_principal = $getPrincipal;
                        $remaining_payment = $byr_angsuran - $remaining_to_principal;
                    } else {
                        $new_payment_value_principal += $byr_angsuran;
                        $remaining_payment = 0;
                    }
                } else {
                    $remaining_payment = $byr_angsuran;
                }

                // Update interest if the principal is fully paid
                if ($new_payment_value_principal == $getPrincipal) {
                    if ($valBeforeInterest < $getInterest) {
                        $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                    }
                }

                // Insert payment details for principal if there is a change
                $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
                if ($valPrincipal > 0) {
                    $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
                    M_PaymentDetail::create($data);
                    $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
                }

                // Insert payment details for interest if there is a change
                $valInterest = $new_payment_value_interest - $valBeforeInterest;
                if ($valInterest > 0) {
                    $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                    M_PaymentDetail::create($data);
                    $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
                }
            }
        }
    }

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    function addCreditPaid($loan_number, array $data)
    {
        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($check_credit) {
            $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK'] : 0;
            $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] : 0;
            $paidPenalty = isset($data['BAYAR_DENDA']) ? $data['BAYAR_DENDA'] : 0;

            $check_credit->update([
                'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
                'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
                'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty)
            ]);
        }
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

    private function insert_customer($request, $data, $cust_code)
    {
        $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
        $cr_order = M_CrOrder::where('APPLICATION_ID', $data->ID)->first();
        $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();

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
            'NAME' => $cr_personal->NAME ?? null,
            'ALIAS' => $cr_personal->ALIAS ?? null,
            'GENDER' => $cr_personal->GENDER ?? null,
            'BIRTHPLACE' => $cr_personal->BIRTHPLACE ?? null,
            'BIRTHDATE' => $cr_personal->BIRTHDATE ?? null,
            'BLOOD_TYPE' => $cr_personal->BLOOD_TYPE ?? null,
            'MOTHER_NAME' => $cr_order->MOTHER_NAME ?? null,
            'NPWP' => $cr_order->NO_NPWP ?? null,
            'MARTIAL_STATUS' => $cr_personal->MARTIAL_STATUS ?? null,
            'MARTIAL_DATE' => $cr_personal->MARTIAL_DATE ?? null,
            'ID_TYPE' => $cr_personal->ID_TYPE ?? null,
            'ID_NUMBER' => $cr_personal->ID_NUMBER ?? null,
            'KK_NUMBER' => $cr_personal->KK ?? null,
            'ID_ISSUE_DATE' => $cr_personal->ID_ISSUE_DATE ?? null,
            'ID_VALID_DATE' => $cr_personal->ID_VALID_DATE ?? null,
            'ADDRESS' => $cr_personal->ADDRESS ?? null,
            'RT' => $cr_personal->RT ?? null,
            'RW' => $cr_personal->RW ?? null,
            'PROVINCE' => $cr_personal->PROVINCE ?? null,
            'CITY' => $cr_personal->CITY ?? null,
            'KELURAHAN' => $cr_personal->KELURAHAN ?? null,
            'KECAMATAN' => $cr_personal->KECAMATAN ?? null,
            'ZIP_CODE' => $cr_personal->ZIP_CODE ?? null,
            'KK' => $cr_personal->KK ?? null,
            'CITIZEN' => $cr_personal->CITIZEN ?? null,
            'INS_ADDRESS' => $cr_personal->INS_ADDRESS ?? null,
            'INS_RT' => $cr_personal->INS_RT ?? null,
            'INS_RW' => $cr_personal->INS_RW ?? null,
            'INS_PROVINCE' => $cr_personal->INS_PROVINCE ?? null,
            'INS_CITY' => $cr_personal->INS_CITY ?? null,
            'INS_KELURAHAN' => $cr_personal->INS_KELURAHAN ?? null,
            'INS_KECAMATAN' => $cr_personal->INS_KECAMATAN ?? null,
            'INS_ZIP_CODE' => $cr_personal->INS_ZIP_CODE ?? null,
            'OCCUPATION' => $cr_personal->OCCUPATION ?? null,
            'OCCUPATION_ON_ID' => $cr_personal->OCCUPATION_ON_ID ?? null,
            'INCOME' => $cr_order->INCOME_PERSONAL ?? null,
            'RELIGION' => $cr_personal->RELIGION ?? null,
            'EDUCATION' => $cr_personal->EDUCATION ?? null,
            'PROPERTY_STATUS' => $cr_personal->PROPERTY_STATUS ?? null,
            'PHONE_HOUSE' => $cr_personal->PHONE_HOUSE ?? null,
            'PHONE_PERSONAL' => $cr_personal->PHONE_PERSONAL ?? null,
            'PHONE_OFFICE' => $cr_personal->PHONE_OFFICE ?? null,
            'EXT_1' => $cr_personal->EXT_1 ?? null,
            'EXT_2' => $cr_personal->EXT_2 ?? null,
            'VERSION' => 1,
            'CREATE_DATE' => Carbon::now(),
            'CREATE_USER' => $request->user()->id ?? 'alex',
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
                    'TYPE' => $res->TYPE ?? null,
                    'BRAND' => $res->BRAND ?? null,
                    'PRODUCTION_YEAR' => $res->PRODUCTION_YEAR ?? null,
                    'COLOR' => $res->COLOR ?? null,
                    'ON_BEHALF' => $res->ON_BEHALF ?? null,
                    'POLICE_NUMBER' => $res->POLICE_NUMBER ?? null,
                    'CHASIS_NUMBER' => $res->CHASIS_NUMBER ?? null,
                    'ENGINE_NUMBER' => $res->ENGINE_NUMBER ?? null,
                    'BPKB_NUMBER' => $res->BPKB_NUMBER ?? null,
                    'BPKB_ADDRESS' => $res->BPKB_ADDRESS ?? null,
                    'STNK_NUMBER' => $res->STNK_NUMBER ?? null,
                    'INVOICE_NUMBER' => $res->INVOICE_NUMBER ?? null,
                    'STNK_VALID_DATE' => $res->STNK_VALID_DATE ?? null,
                    'VALUE' => $res->VALUE ?? null,
                    'LOCATION_BRANCH' => $data->BRANCH,
                    'COLLATERAL_FLAG' => $data->BRANCH,
                    'VERSION' => 1,
                    'CREATE_DATE' => $this->timeNow,
                    'CREATE_BY' => '61',
                ];

                $execute = M_CrCollateral::create($data_jaminan);

                $statusLog = 'NEW ' . $loan_number ?? '';

                M_LocationStatus::create([
                    'TYPE' => 'kendaraan',
                    'COLLATERAL_ID' => $execute->ID,
                    'LOCATION' => $data->BRANCH,
                    'STATUS' => $statusLog,
                    'CREATE_BY' => '66',
                    'CREATED_AT' => now(),
                ]);

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
}

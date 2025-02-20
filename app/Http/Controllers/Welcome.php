<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\LocationStatus;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PelunasanController;
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
use App\Models\M_KwitansiDetailPelunasan;
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

use function Symfony\Component\Mailer\Event\getMessage;

class Welcome extends Controller
{
    protected $locationStatus;

    public function __construct(LocationStatus $locationStatus)
    {
        $this->locationStatus = $locationStatus;
    }

    public function index(Request $req)
    {

        // return response()->json('OK');
        // die;
        DB::beginTransaction();
        try {

            $inv = $req->no_invoice;
            $type = $req->tipe;
            $setDate = $req->tgl;

            if ($type == 'angsuran') {
                $queryAngsuran = "  SELECT  a.NO_TRANSAKSI,
                                a.LOAN_NUMBER,
                                a.PAYMENT_TYPE,
                                a.METODE_PEMBAYARAN,
                                a.BRANCH_CODE,
                                a.TGL_TRANSAKSI,
                                a.CREATED_BY,
                                a.CREATED_AT,
                                a.PINALTY_PELUNASAN,
                                a.DISKON_PINALTY_PELUNASAN,
                                b.*
                    FROM kwitansi a
                        LEFT JOIN kwitansi_structur_detail b 
                        ON b.no_invoice = a.NO_TRANSAKSI
                    WHERE a.STTS_PAYMENT = 'PAID'
                        AND a.NO_TRANSAKSI = '$inv'
                        AND a.PAYMENT_TYPE = 'angsuran'
                    --    AND a.CREATED_AT > str_to_date('$setDate','%Y%m%d')
                        AND (b.installment != 0 OR b.bayar_angsuran != 0 OR b.bayar_denda != 0 OR b.diskon_denda != 0)  
					ORDER BY a.LOAN_NUMBER,a.TGL_TRANSAKSI ASC";

                $resultsAngsuran = DB::select($queryAngsuran);

                if (empty($resultsAngsuran)) {
                    throw new Exception("Invoice Not Found", 404);
                }

                $structuredDataAngsuran = [];

                foreach ($resultsAngsuran as $result) {

                    if (!isset($structuredDataAngsuran[$result->NO_TRANSAKSI])) {
                        $structuredDataAngsuran[$result->NO_TRANSAKSI] = [
                            'payment_type' => $result->PAYMENT_TYPE,
                            'payment_method' => $result->METODE_PEMBAYARAN,
                            'no_transaksi' => $result->NO_TRANSAKSI,
                            'no_fasilitas' => $result->LOAN_NUMBER,
                            'bayar_pinalty' => $result->PINALTY_PELUNASAN,
                            'diskon_pinalty' => $result->DISKON_PINALTY_PELUNASAN,
                            'cabang' =>  $result->BRANCH_CODE,
                            'created_by' => $result->CREATED_BY,
                            'created_at' => $result->CREATED_AT,
                            'struktur' => [],
                        ];
                    }

                    $structuredDataAngsuran[$result->NO_TRANSAKSI]['struktur'][] = [
                        'id' => $result->id,
                        'no_invoice' => $result->NO_TRANSAKSI,
                        'key' => $result->key,
                        'angsuran_ke' => $result->angsuran_ke,
                        'loan_number' => $result->LOAN_NUMBER,
                        'tgl_angsuran' => $result->tgl_angsuran,
                        'principal' => $result->principal,
                        'interest' => $result->interest,
                        'installment' => $result->installment,
                        'principal_remains' => $result->principal_remains,
                        'payment' => $result->payment,
                        'bayar_angsuran' => $result->bayar_angsuran,
                        'bayar_denda' => $result->bayar_denda,
                        'total_bayar' => $result->total_bayar,
                        'flag' => $result->flag,
                        'denda' => $result->denda,
                        'diskon_denda' => $result->diskon_denda,
                    ];
                }

                $arrearsData = [];
                foreach ($structuredDataAngsuran as $request) {
                    $struktur = $request['struktur'];

                    if (isset($struktur) && is_array($struktur)) {
                        foreach ($struktur as $res) {

                            $paymntDate = date('Y-m-d', strtotime($res['tgl_angsuran']));

                            $getCrditSchedule = "   SELECT LOAN_NUMBER,PAYMENT_DATE,PRINCIPAL,INTEREST,INSTALLMENT
                                                    FROM credit_schedule 
                                                    WHERE PAYMENT_DATE = '$paymntDate' 
                                                        AND LOAN_NUMBER = '{$res['loan_number']}'
                                                        AND (PAID_FLAG IS NULL OR PAID_FLAG = '') ";


                            $updateArrears = DB::select($getCrditSchedule);

                            foreach ($updateArrears as $list) {
                                $date = date('Y-m-d', strtotime($request['created_at']));
                                $daysDiff = (strtotime($date) - strtotime($list->PAYMENT_DATE)) / (60 * 60 * 24);
                                $pastDuePenalty = $list->INSTALLMENT * ($daysDiff * 0.005);

                                $arrearsData[] = [
                                    'ID' => Uuid::uuid7()->toString(),
                                    'STATUS_REC' => 'A',
                                    'LOAN_NUMBER' => $list->LOAN_NUMBER,
                                    'START_DATE' => $list->PAYMENT_DATE,
                                    'END_DATE' => null,
                                    'PAST_DUE_PCPL' => $list->PRINCIPAL ?? 0,
                                    'PAST_DUE_INTRST' => $list->INTEREST ?? 0,
                                    'PAST_DUE_PENALTY' => $pastDuePenalty ?? 0,
                                    'PAID_PCPL' => 0,
                                    'PAID_INT' => 0,
                                    'PAID_PENALTY' => 0,
                                    'CREATED_AT' => Carbon::now('Asia/Jakarta')
                                ];
                            }
                        }
                    }
                }

                foreach ($arrearsData as $data) {
                    $existingArrears = M_Arrears::where([
                        'LOAN_NUMBER' => $data['LOAN_NUMBER'],
                        'START_DATE' => $data['START_DATE'],
                        'STATUS_REC' => 'A'
                    ])->first();

                    if ($existingArrears) {
                        $existingArrears->update([
                            'PAST_DUE_PENALTY' => $data['PAST_DUE_PENALTY'] ?? 0,
                            'UPDATED_AT' => Carbon::now('Asia/Jakarta')
                        ]);
                    } else {
                        M_Arrears::create($data);
                    }
                }

                foreach ($structuredDataAngsuran as $request) {
                    $no_inv = $request['no_transaksi'];
                    $loan_number = $request['no_fasilitas'];
                    $getCodeBranch = M_Branch::findOrFail($request['cabang']);
                    $struktur = $request['struktur'];

                    if (isset($struktur) && is_array($struktur)) {
                        foreach ($struktur as $res) {

                            M_KwitansiStructurDetail::firstOrCreate([
                                'no_invoice' => $no_inv,
                                'key' => $res['key'] ?? '',
                                'loan_number' => $res['loan_number'] ?? ''
                            ], [
                                'angsuran_ke' => $res['angsuran_ke'] ?? '',
                                'tgl_angsuran' => $res['tgl_angsuran'] ?? '',
                                'principal' => $res['principal'] ?? '',
                                'interest' => $res['interest'] ?? '',
                                'installment' => $res['installment'] ?? '',
                                'principal_remains' => $res['principal_remains'] ?? '',
                                'payment' => $res['payment'] ?? '',
                                'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
                                'bayar_denda' => $res['bayar_denda'] ?? '',
                                'total_bayar' => $res['total_bayar'] ?? '',
                                'flag' => $res['flag'] ?? '',
                                'denda' => $res['denda'] ?? '',
                                'diskon_denda' => $res['diskon_denda']
                            ]);

                            $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv);
                        }
                    }
                }
            } else {
                $queryPelunasan = "  SELECT  a.NO_TRANSAKSI,
                                a.LOAN_NUMBER,
                                a.PAYMENT_TYPE,
                                a.METODE_PEMBAYARAN,
                                a.BRANCH_CODE,
                                a.TGL_TRANSAKSI,
                                a.CREATED_BY,
                                a.CREATED_AT,
                                a.PINALTY_PELUNASAN,
                                a.DISKON_PINALTY_PELUNASAN,
                                b.*
                    FROM kwitansi a
                        LEFT JOIN kwitansi_structur_detail b 
                        ON b.no_invoice = a.NO_TRANSAKSI
                    WHERE a.STTS_PAYMENT = 'PAID'
                        AND a.LOAN_NUMBER = '11103230000086'
                        AND a.PAYMENT_TYPE = 'pelunasan'
                       AND a.CREATED_AT > str_to_date('20250201','%Y%m%d')
					ORDER BY a.LOAN_NUMBER,a.TGL_TRANSAKSI ASC";

                $resultsPelunasan = DB::select($queryPelunasan);

                $structuredDataPelunasan = [];

                foreach ($resultsPelunasan as $result) {

                    if (!isset($structuredDataPelunasan[$result->NO_TRANSAKSI])) {
                        $structuredDataPelunasan[$result->NO_TRANSAKSI] = [
                            'payment_type' => $result->PAYMENT_TYPE,
                            'payment_method' => $result->METODE_PEMBAYARAN,
                            'no_transaksi' => $result->NO_TRANSAKSI,
                            'no_fasilitas' => $result->LOAN_NUMBER,
                            'bayar_pinalty' => $result->PINALTY_PELUNASAN,
                            'diskon_pinalty' => $result->DISKON_PINALTY_PELUNASAN,
                            'cabang' =>  $result->BRANCH_CODE,
                            'created_by' => $result->CREATED_BY,
                            'created_at' => $result->CREATED_AT,
                            'struktur' => [],
                        ];
                    }

                    $structuredDataPelunasan[$result->NO_TRANSAKSI]['struktur'][] = [
                        'id' => $result->id,
                        'no_invoice' => $result->NO_TRANSAKSI,
                        'key' => $result->key,
                        'angsuran_ke' => $result->angsuran_ke,
                        'loan_number' => $result->LOAN_NUMBER,
                        'tgl_angsuran' => $result->tgl_angsuran,
                        'principal' => $result->principal,
                        'interest' => $result->interest,
                        'installment' => $result->installment,
                        'principal_remains' => $result->principal_remains,
                        'payment' => $result->payment,
                        'bayar_angsuran' => $result->bayar_angsuran,
                        'bayar_denda' => $result->bayar_denda,
                        'total_bayar' => $result->total_bayar,
                        'flag' => $result->flag,
                        'denda' => $result->denda,
                        'diskon_denda' => $result->diskon_denda,
                    ];
                }

                foreach ($structuredDataPelunasan as $request) {
                    $no_inv = $request['no_transaksi'];
                    $loan_number = $request['no_fasilitas'];
                    $getCodeBranch = M_Branch::findOrFail($request['cabang']);
                    $struktur = $request['struktur'];

                    if (isset($struktur) && is_array($struktur)) {
                        foreach ($struktur as $res) {
                            $this->proccess($request, $loan_number, $no_inv, 'PAID');
                        }
                    }
                }
            }

            DB::commit();
            return response()->json("MUACHHHHHHHHHHHHHH");
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    //  $groupedData = [];

    // function telBot()
    // {
    //    // $newMessages = M_TelegramBotSend::where('status', 'new')->get();

    // foreach ($newMessages as $message) {
    //     $getJson = $message->messages;
    //     if (!empty($getJson)) {
    //         $convert = json_decode($getJson, true);
    //         $findBRanch = M_Branch::find($convert['BRANCH_CODE']);

    //         // Wrap ternary operator in parentheses for correct evaluation
    //         $buildMsg = " MINTA APPROVAL PEMBAYARAN" . "\n" .
    //             'Tipe = ' . $convert['PAYMENT_TYPE'] . "\n" .
    //             'Status = Pending' . "\n" .
    //             'No Transaksi = ' . $convert['NO_TRANSAKSI'] . "\n" .
    //             'No Kontrak = ' . $convert['LOAN_NUMBER'] . "\n" .
    //             'Cabang = ' . ($findBRanch ? $findBRanch->NAME : '') . "\n" .
    //             'Tgl Transaksi = ' . $convert['TGL_TRANSAKSI'];

    //         TelegramBotConfig::sendMessage($buildMsg);

    //         $update = M_TelegramBotSend::find($message->id);

    //         if ($update) {
    //             $update->update(['status' => 'send']);
    //         }
    //     }
    // }
    // }

    // foreach ($request->all() as $item) {
    //     // Use no_invoice and angsuran_ke as the key for grouping
    //     $key = $item['no_invoice'] . '-' . $item['angsuran_ke'];

    //     // If the group doesn't exist yet, initialize it
    //     if (!isset($groupedData[$key])) {
    //         // Initialize the group for this invoice and angsuran_ke
    //         $groupedData[$key] = [
    //             "type" => $item['PAYMENT_TYPE'],
    //             "status" => $item['STTS_PAYMENT'],
    //             "method" => $item['METODE_PEMBAYARAN'],
    //             "time" => $item['CREATED_AT'],
    //             "by" => $item['CREATED_BY'],
    //             "branch" => $item['BRANCH_CODE'],
    //             "loan" => $item['LOAN_NUMBER'],
    //             "invoice" => $item['no_invoice'],  // keep original no_invoice
    //             "tgl_angsuran" => $item['tgl_angsuran'],  // assuming we want to keep the latest tgl_angsuran for each LOAN_NUMBER
    //             "angsuran_ke" => $item['angsuran_ke'],
    //             "installment" => $item['installment'],
    //             "diskon_denda" => $item['diskon_denda'],
    //             "flag" => $item['flag'],
    //             'details' => [],
    //         ];
    //     }

    //     // Prepare the detail entry based on bayar_denda
    //     if ($item['bayar_denda'] != 0) {
    //         // Add both bayar_angsuran and bayar_denda if bayar_denda is not 0
    //         $detail = [
    //             'bayar_angsuran' => $item['bayar_angsuran'],
    //             'bayar_denda' => $item['bayar_denda'],
    //         ];
    //     } else {
    //         // If bayar_denda is 0, only add bayar_angsuran
    //         $detail = [
    //             'bayar_angsuran' => $item['bayar_angsuran'],
    //         ];
    //     }

    //     // Add the detail to the group's details
    //     $groupedData[$key]['details'][] = $detail;
    // }

    // foreach ($groupedData as $data) {
    //     // $uid = Uuid::uuid7()->toString();

    //     // M_Payment::create([
    //     //     'ID' => $uid,
    //     //     'ACC_KEY' => $data['flag'] == 'PAID' ? 'angsuran_denda' : $data['type'] ?? '',
    //     //     'STTS_RCRD' => 'PAID',
    //     //     'INVOICE' => $data['invoice'],
    //     //     'NO_TRX' => $request->uid,
    //     //     'PAYMENT_METHOD' => $data['method'],
    //     //     'BRANCH' => $data["branch"],
    //     //     'LOAN_NUM' => $data['loan'],
    //     //     'VALUE_DATE' => null,
    //     //     'ENTRY_DATE' => $data["time"],
    //     //     'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
    //     //     'TITLE' => 'Angsuran Ke-' . $data['angsuran_ke'],
    //     //     'ORIGINAL_AMOUNT' => $data['installment'],
    //     //     'OS_AMOUNT' => $os_amount ?? 0,
    //     //     'START_DATE' => date('Y-m-d', strtotime($data['tgl_angsuran'])),
    //     //     'END_DATE' => $data["time"],
    //     //     'USER_ID' => $data["by"],
    //     //     'AUTH_BY' => $data["by"],
    //     //     'AUTH_DATE' => $data["time"],
    //     //     'ARREARS_ID' => $data['id_arrear'] ?? '',
    //     //     'BANK_NAME' => round(microtime(true) * 1000)
    //     // ]);

    //     $get = M_Payment::where(['LOAN_NUM' => $data["loan"], 'INVOICE' => $data["invoice"], 'TITLE' => 'Angsuran Ke-' . $data['angsuran_ke']])->first();

    //     $checkDetail = M_PaymentDetail::where(['PAYMENT_ID' => $get->ID])->first();

    //     if (!$checkDetail) {
    //         $this->updateCreditSchedule($data['loan'], $data['tgl_angsuran'], $data, $get->ID ?? 0);
    //     }
    // }


    // return response()->json('ok', 200);

    // private function lala()
    // {
    //     $data = M_CrApplication::where('ORDER_NUMBER', $request->order_number)->first();

    //     $set_tgl_awal = $request->tgl_awal;

    //     $type = $data->INSTALLMENT_TYPE;

    //     // if (strtolower($type) == 'bulanan') {
    //     //     $data_credit_schedule = $this->generateAmortizationSchedule($set_tgl_awal, $data);
    //     // } else {
    //     //     $data_credit_schedule = $this->generateAmortizationScheduleMusiman($set_tgl_awal, $data);
    //     // }

    //     // $no = 1;
    //     // foreach ($data_credit_schedule as $list) {
    //     //     $credit_schedule =
    //     //         [
    //     //             'ID' => Uuid::uuid7()->toString(),
    //     //             'LOAN_NUMBER' => $request->loan_number ?? '',
    //     //             'INSTALLMENT_COUNT' => $no++,
    //     //             'PAYMENT_DATE' => parseDatetoYMD($list['tgl_angsuran']),
    //     //             'PRINCIPAL' => $list['pokok'],
    //     //             'INTEREST' => $list['bunga'],
    //     //             'INSTALLMENT' => $list['total_angsuran'],
    //     //             'PRINCIPAL_REMAINS' => $list['baki_debet']
    //     //         ];

    //     //     M_CreditSchedule::create($credit_schedule);
    //     // }

    //     $check_exist = M_Credit::where('ORDER_NUMBER', $request->order_number)->first();
    //     if ($check_exist) {
    //         $SET_UUID = $check_exist->ID;
    //         $cust_code = $check_exist->CUST_CODE;

    //         // $this->insert_customer($request, $data, $cust_code);
    //         $this->insert_customer_xtra($data, $cust_code);
    //         $this->insert_collateral($request, $data, $SET_UUID, $request->loan_number);
    //     }
    // }

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        $uid = Uuid::uuid7()->toString();

        $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid);

        if (isset($res['diskon_denda']) && $res['diskon_denda'] == 1) {
            $this->updateDiscountArrears($request, $loan_number, $tgl_angsuran, $res, $uid);
        } else {
            $this->updateArrears($request, $loan_number, $tgl_angsuran, $res, $uid);
        }

        if ($res['bayar_angsuran'] != 0 || $res['bayar_denda'] != 0 || (isset($res['diskon_denda']) && $res['diskon_denda'] == 1)) {
            $this->createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch, $uid);
        }

        $this->updateCredit($loan_number);
    }

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        if (!$credit_schedule) {
            throw new Exception("Credit Schedule Not Found", 404);
        }

        $byr_angsuran = $res['bayar_angsuran'];
        $flag = $res['flag'];

        if ($credit_schedule || $byr_angsuran != 0 || $flag != 'PAID') {

            $payment_value = $byr_angsuran + $credit_schedule->PAYMENT_VALUE;

            $valBeforePrincipal = $credit_schedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $credit_schedule->PAYMENT_VALUE_INTEREST;
            $getPrincipal = $credit_schedule->PRINCIPAL;
            $getInterest = $credit_schedule->INTEREST;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

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

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;

                $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;

                $valInterest = $new_payment_value_interest - $valBeforeInterest;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
            }

            $total_paid = $new_payment_value_principal + $new_payment_value_interest;

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                ? ($total_paid - $credit_schedule->INSTALLMENT)
                : 0;

            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;
            $updates['PAYMENT_VALUE'] = $payment_value;

            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            $credit_schedule->update(['PAID_FLAG' => $credit_schedule->PAYMENT_VALUE >= $credit_schedule->INSTALLMENT ? 'PAID' : '']);
        }
    }

    private function updateCredit($loan_number)
    {

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if (!$check_credit) {
            throw new Exception("Credit Not Found", 404);
        }

        $checkCreditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->get();

        $checkArrears = M_Arrears::where('LOAN_NUMBER', $loan_number)
            ->whereIn('STATUS_REC', ['A', 'PENDING'])
            ->get();

        if ($checkCreditSchedule->isEmpty() && $checkArrears->isEmpty()) {
            $status = 'D';
            $status_rec = 'CL';
        } else {
            $status = 'A';
        }

        if ($check_credit) {
            $check_credit->update([
                'STATUS' => $status,
                'STATUS_REC' => $status_rec ?? 'AC',
            ]);
        }
    }

    private function updateDiscountArrears($request, $loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        if ($check_arrears) {
            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

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

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $paymentData = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($paymentData);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $remainingPenalty = floatval($getPenalty) - floatval($bayar_denda);
            if ($remainingPenalty > 0) {
                $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $remainingPenalty);
                M_PaymentDetail::create($discountPaymentData);
                $this->addCreditPaid($loan_number, ['DISKON_DENDA' => $remainingPenalty]);
            }

            $updates['PAID_PENALTY'] = $getPenalty;
            $updates['END_DATE'] = $request['created_at'];
            $updates['UPDATED_AT'] = $request['created_at'];
            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $check_arrears->update(['STATUS_REC' => $remainingPenalty > 0 ? 'D' : 'S']);
        }
    }

    private function updateArrears($request, $loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = $res['bayar_angsuran'];
        $bayar_denda = $res['bayar_denda'];

        if ($check_arrears || $res['bayar_denda'] != 0) {
            $current_penalty = $check_arrears->PAID_PENALTY;

            $new_penalty = $current_penalty + $bayar_denda;

            $valBeforePrincipal = $check_arrears->PAID_PCPL;
            $valBeforeInterest = $check_arrears->PAID_INT;
            $getPrincipal = $check_arrears->PAST_DUE_PCPL;
            $getInterest = $check_arrears->PAST_DUE_INTRST;
            $getPenalty = $check_arrears->PAST_DUE_PENALTY;

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_interest = $valBeforeInterest;

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

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($data);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $updates['PAID_PENALTY'] = $new_penalty;
            $updates['END_DATE'] = $request['created_at'];
            $updates['UPDATED_AT'] = $request['created_at'];
            $updates['STATUS_REC'] = 'A';

            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $total1 = floatval($new_payment_value_principal) + floatval($new_payment_value_interest) + floatval($new_penalty);
            $total2 = floatval($getPrincipal) + floatval($getInterest) + floatval($getPenalty);

            if ($total1 == $total2 || $new_penalty > $getPenalty) {
                $check_arrears->update(['STATUS_REC' => 'S']);
            }
        }
    }

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $uid)
    {

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => $res['flag'] == 'PAID' ? 'angsuran_denda' : $request['payment_type'] ?? '',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'BRANCH' => $branch->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => $request['created_at'],
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran,
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at'],
            'ARREARS_ID' => $res['id_arrear'] ?? '',
            'BANK_NAME' => round(microtime(true) * 1000)
        ]);
    }

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    public function addCreditPaid($loan_number, array $data)
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

    function proccess($request, $loan_number, $no_inv, $status)
    {
        $pelunasanKwitansiDetail = M_KwitansiDetailPelunasan::where(['no_invoice' => $no_inv, 'loan_number' => $loan_number])->get();

        $this->proccessPinaltyPayment($request, $no_inv, $status, $loan_number);

        if (!empty($pelunasanKwitansiDetail)) {
            foreach ($pelunasanKwitansiDetail as $res) {
                $uid = Uuid::uuid7()->toString();
                $this->proccessPayment($request, $uid, $no_inv, $status, $res);

                $paymentDetails = [
                    'BAYAR_POKOK' => $res['bayar_pokok'] ?? 0,
                    'BAYAR_BUNGA' => $res['bayar_bunga'] ?? 0,
                    'BAYAR_DENDA' => $res['bayar_denda'] ?? 0,
                    'DISKON_POKOK' => $res['diskon_pokok'] ?? 0,
                    'DISKON_BUNGA' => $res['diskon_bunga'] ?? 0,
                    'DISKON_DENDA' => $res['diskon_denda'] ?? 0,
                ];

                foreach ($paymentDetails as $type => $amount) {
                    if ($amount != 0) {
                        $this->proccessPaymentDetail($uid, $type, $amount);
                    }
                }

                if ($res['installment'] != 0) {
                    $this->updateCreditSchedulePelunasan($loan_number, $res);
                }

                $this->updateArrearsPelunasan($loan_number, $res);
                $this->updateCreditPelunasan($res, $loan_number);
            }
        }
    }

    function proccessPaymentDetail($payment_id, $acc_key, $amount)
    {
        M_PaymentDetail::create([
            'ID' => Uuid::uuid7()->toString(),
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ]);
    }

    function proccessPayment($request, $uid, $no_inv, $status, $res)
    {
        $originalAmount = (
            ($res['bayar_pokok'] ?? 0) +
            ($res['bayar_bunga'] ?? 0) +
            ($res['bayar_denda'] ?? 0) +
            ($res['diskon_pokok'] ?? 0) +
            ($res['diskon_bunga'] ?? 0) +
            ($res['diskon_denda'] ?? 0));

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Pelunasan Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::findOrFail($request['cabang'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $res['loan_number'] ?? '',
            'ENTRY_DATE' => $request['created_at'],
            'TITLE' => 'Angsuran Ke-' . ($res['angsuran_ke'] ?? ''),
            'ORIGINAL_AMOUNT' => $originalAmount,
            'START_DATE' => $res['tgl_angsuran'] ?? '',
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at']
        ]);
    }

    function proccessPinaltyPayment($request, $no_inv, $status, $loan_number)
    {
        $uid = Uuid::uuid7()->toString();

        M_Payment::create([
            'ID' => $uid,
            'ACC_KEY' => 'Bayar Pelunasan Pinalty',
            'STTS_RCRD' => $status,
            'NO_TRX' => $no_inv,
            'PAYMENT_METHOD' => $request['payment_method'] ?? '',
            'INVOICE' => $no_inv,
            'BRANCH' => M_Branch::findOrFail($request['cabang'])->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number ?? '',
            'ENTRY_DATE' => $request['created_at'],
            'TITLE' => 'Bayar Pelunasan Pinalty',
            'ORIGINAL_AMOUNT' => $request['bayar_pinalty'] ?? 0,
            'END_DATE' => $request['created_at'],
            'USER_ID' => $request['created_by'],
            'AUTH_BY' => 'NOVA',
            'AUTH_DATE' => $request['created_at']
        ]);

        if ($request['bayar_pinalty'] != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN PINALTY', $request['bayar_pinalty'] ?? 0);
        }

        if ($request['diskon_pinalty'] != 0) {
            $this->proccessPaymentDetail($uid, 'BAYAR PELUNASAN DISKON PINALTY', $request['diskon_pinalty'] ?? 0);
        }
    }

    function updateCreditSchedulePelunasan($loan_number, $res)
    {

        $getCreditSchedule = M_CreditSchedule::where(['LOAN_NUMBER' => $loan_number, 'PAYMENT_DATE' => $res['tgl_angsuran']])->first();

        if ($getCreditSchedule) {

            $valBeforePrincipal = $getCreditSchedule->PAYMENT_VALUE_PRINCIPAL;
            $valBeforeInterest = $getCreditSchedule->PAYMENT_VALUE_INTEREST;
            $valBeforeDiscPrincipal = $getCreditSchedule->DISCOUNT_PRINCIPAL;
            $valBeforeDiscInterest = $getCreditSchedule->DISCOUNT_INTEREST;

            $ttlPrincipal = floatval($valBeforePrincipal) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($valBeforeInterest) + floatval($res['bayar_bunga'] ?? 0);
            $ttlDiscPrincipal = floatval($valBeforeDiscPrincipal) + floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($valBeforeDiscInterest) + floatval($res['diskon_bunga'] ?? 0);

            $getCreditSchedule->update([
                'PAYMENT_VALUE_PRINCIPAL' => $ttlPrincipal,
                'PAYMENT_VALUE_INTEREST' => $ttlInterest,
                'DISCOUNT_PRINCIPAL' => $ttlDiscPrincipal,
                'DISCOUNT_INTEREST' => $ttlDiscInterest,
                'PAID_FLAG' => 'PAID'
            ]);

            $ttlPayment = $ttlPrincipal + $ttlInterest + $ttlDiscPrincipal + $ttlDiscInterest;

            $getCreditSchedule->update([
                'PAYMENT_VALUE' => $ttlPayment
            ]);
        }
    }

    function updateArrearsPelunasan($loan_number, $res)
    {
        $getArrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $res['tgl_angsuran'],
        ])->first();

        if ($getArrears) {
            $ttlPrincipal = floatval($getArrears->PAID_PCPL) + floatval($res['bayar_pokok'] ?? 0);
            $ttlInterest = floatval($getArrears->PAID_INT) + floatval($res['bayar_bunga'] ?? 0);
            $ttlPenalty = floatval($getArrears->PAID_PENALTY) + floatval($res['bayar_denda'] ?? 0);
            $ttlDiscPrincipal = floatval($getArrears->WOFF_PCPL) + floatval($res['diskon_pokok'] ?? 0);
            $ttlDiscInterest = floatval($getArrears->WOFF_INT) + floatval($res['diskon_bunga'] ?? 0);
            $ttlDiscPenalty = floatval($getArrears->WOFF_PENALTY) + floatval($res['diskon_denda'] ?? 0);

            $getArrears->update([
                'END_DATE' => Carbon::now()->format('Y-m-d') ?? null,
                'PAID_PCPL' => $ttlPrincipal ?? 0,
                'PAID_INT' => $ttlInterest ?? 0,
                'PAID_PENALTY' => $ttlPenalty ?? 0,
                'WOFF_PCPL' => $ttlDiscPrincipal ?? 0,
                'WOFF_INT' => $ttlDiscInterest ?? 0,
                'WOFF_PENALTY' => $ttlDiscPenalty ?? 0,
                'UPDATED_AT' => Carbon::now(),
            ]);

            $checkDiscountArrears = ($ttlDiscPrincipal + $ttlDiscInterest + $ttlDiscPenalty) == 0;

            $getArrears->update([
                'STATUS_REC' => $checkDiscountArrears ? 'S' : 'D',
            ]);
        }
    }

    function updateCreditPelunasan($res, $loan_number)
    {
        $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->first();

        if ($credit) {
            $credit->update([
                'PAID_PRINCIPAL' => floatval($credit->PAID_PRINCIPAL) + floatval($res['bayar_pokok']),
                'PAID_INTEREST' => floatval($credit->PAID_INTEREST) + floatval($res['bayar_bunga']),
                'DISCOUNT_PRINCIPAL' => floatval($credit->DISCOUNT_PRINCIPAL) + floatval($res['diskon_pokok']),
                'DISCOUNT_INTEREST' => floatval($credit->DISCOUNT_INTEREST) + floatval($res['diskon_bunga']),
                'PAID_PENALTY' => floatval($credit->PAID_PENALTY) + floatval($res['bayar_denda']),
                'DISCOUNT_PENALTY' => floatval($credit->DISCOUNT_PENALTY) + floatval($res['diskon_denda']),
                'STATUS' => 'D',
                'STATUS_REC' => 'PT',
                'END_DATE' => now()
            ]);
        }
    }


    // function updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid)
    // {
    //     $credit_schedule = M_CreditSchedule::where([
    //         'LOAN_NUMBER' => $loan_number,
    //         'PAYMENT_DATE' => date('Y-m-d', strtotime($tgl_angsuran))
    //     ])->first();

    //     if ($credit_schedule) {
    //         $byr_angsuran = $res['details'][0]['bayar_angsuran'] ?? $res['bayar_angsuran'];

    //         $valBeforePrincipal = $credit_schedule ? $credit_schedule->PAYMENT_VALUE_PRINCIPAL : 0;
    //         $valBeforeInterest = $credit_schedule ? $credit_schedule->PAYMENT_VALUE_INTEREST : 0;
    //         $getPrincipal = $credit_schedule ? $credit_schedule->PRINCIPAL : 0;
    //         $getInterest = $credit_schedule ? $credit_schedule->INTEREST : 0;

    //         if ($credit_schedule->PAID_FLAG == 'PAID') {
    //             $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $getPrincipal);
    //             M_PaymentDetail::create($data);
    //             $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $getPrincipal]);

    //             $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $getInterest);
    //             M_PaymentDetail::create($data);
    //             $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $getInterest]);
    //         } else {
    //             $new_payment_value_principal = $valBeforePrincipal;
    //             $new_payment_value_interest = $valBeforeInterest;

    //             // Process principal payment if needed
    //             if ($valBeforePrincipal < $getPrincipal) {
    //                 $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

    //                 if ($byr_angsuran >= $remaining_to_principal) {
    //                     $new_payment_value_principal = $getPrincipal;
    //                     $remaining_payment = $byr_angsuran - $remaining_to_principal;
    //                 } else {
    //                     $new_payment_value_principal += $byr_angsuran;
    //                     $remaining_payment = 0;
    //                 }
    //             } else {
    //                 $remaining_payment = $byr_angsuran;
    //             }

    //             // Update interest if the principal is fully paid
    //             if ($new_payment_value_principal == $getPrincipal) {
    //                 if ($valBeforeInterest < $getInterest) {
    //                     $new_payment_value_interest = min($valBeforeInterest + $remaining_payment, $getInterest);
    //                 }
    //             }

    //             // Insert payment details for principal if there is a change
    //             $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
    //             if ($valPrincipal > 0) {
    //                 $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
    //                 M_PaymentDetail::create($data);
    //                 $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
    //             }

    //             // Insert payment details for interest if there is a change
    //             $valInterest = $new_payment_value_interest - $valBeforeInterest;
    //             if ($valInterest > 0) {
    //                 $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
    //                 M_PaymentDetail::create($data);
    //                 $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
    //             }
    //         }
    //     }
    // }

    // function preparePaymentData($payment_id, $acc_key, $amount)
    // {
    //     return [
    //         'PAYMENT_ID' => $payment_id,
    //         'ACC_KEYS' => $acc_key,
    //         'ORIGINAL_AMOUNT' => $amount
    //     ];
    // }

    // function addCreditPaid($loan_number, array $data)
    // {
    //     $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

    //     if ($check_credit) {
    //         $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK'] : 0;
    //         $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] : 0;
    //         $paidPenalty = isset($data['BAYAR_DENDA']) ? $data['BAYAR_DENDA'] : 0;

    //         $check_credit->update([
    //             'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
    //             'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
    //             'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty)
    //         ]);
    //     }
    // }

    // private function generateAmortizationSchedule($setDate, $data)
    // {
    //     $schedule = [];
    //     $remainingBalance = $data->POKOK_PEMBAYARAN;
    //     $term = ceil($data->TENOR);
    //     $angsuran = $data->INSTALLMENT;
    //     $suku_bunga_konversi = ($data->FLAT_RATE / 100);
    //     $ttal_bunga = $data->TOTAL_INTEREST;
    //     $totalInterestPaid = 0;

    //     for ($i = 1; $i <= $term; $i++) {
    //         $interest = round($remainingBalance * $suku_bunga_konversi, 2);

    //         if ($i < $term) {
    //             $principalPayment = round($angsuran - $interest, 2);
    //         } else {
    //             $principalPayment = round($remainingBalance, 2);
    //             $interest = round($ttal_bunga - $totalInterestPaid, 2);
    //         }

    //         $totalPayment = round($principalPayment + $interest, 2);
    //         $remainingBalance = round($remainingBalance - $principalPayment, 2);
    //         $totalInterestPaid += $interest;
    //         if ($i == $term) {
    //             $remainingBalance = 0.00;
    //         }

    //         $schedule[] = [
    //             'angsuran_ke' => $i,
    //             'tgl_angsuran' => setPaymentDate($setDate, $i),
    //             'baki_debet_awal' => floatval($remainingBalance + $principalPayment),
    //             'pokok' => floatval($principalPayment),
    //             'bunga' => floatval($interest),
    //             'total_angsuran' => floatval($totalPayment),
    //             'baki_debet' => floatval($remainingBalance)
    //         ];
    //     }

    //     return $schedule;
    // }

    // private function generateAmortizationScheduleMusiman($setDate, $data)
    // {
    //     $schedule = [];
    //     $remainingBalance = $data->POKOK_PEMBAYARAN;  // Initial loan amount (POKOK_PEMBAYARAN)
    //     $term = ceil($data->TENOR);  // Loan term in months (TENOR)
    //     $angsuran = $data->INSTALLMENT;  // Monthly installment (INSTALLMENT)
    //     $suku_bunga_konversi = round($data->FLAT_RATE / 100, 10);  // Monthly interest rate (FLAT_RATE divided by 100)
    //     $ttal_bunga = $data->TOTAL_INTEREST;  // Total interest (TOTAL_INTEREST)
    //     $totalInterestPaid = 0;  // Total interest paid so far

    //     $tenorList = [
    //         '3' => 1,
    //         '6' => 1,
    //         '12' => 2,
    //         '18' => 3
    //     ];

    //     $term = $tenorList[$term] ?? 0;

    //     $monthsToAdd = ($data->TENOR / $tenorList[$data->TENOR]) ?? 0;

    //     $startDate = new DateTime($setDate);

    //     for ($i = 1; $i <= $term; $i++) {

    //         $interest = round($remainingBalance * $suku_bunga_konversi, 2);

    //         if ($i < $term) {
    //             $principalPayment = round($angsuran - $interest, 2);
    //         } else {
    //             $principalPayment = round($remainingBalance, 2);
    //             $interest = round($ttal_bunga - $totalInterestPaid, 2);
    //         }

    //         $totalPayment = round($principalPayment + $interest, 2);
    //         $remainingBalance = round($remainingBalance - $principalPayment, 2);
    //         $totalInterestPaid += $interest;

    //         if ($i == $term) {
    //             $remainingBalance = 0.00;
    //         }

    //         $paymentDate = clone $startDate;

    //         $paymentDate->modify("+{$monthsToAdd} months");

    //         // Format the date as required (e.g., 'Y-m-d')
    //         $formattedPaymentDate = $paymentDate->format('Y-m-d');

    //         $schedule[] = [
    //             'angsuran_ke' => $i,
    //             'tgl_angsuran' => $formattedPaymentDate,
    //             'pokok' => floatval($principalPayment),
    //             'bunga' => floatval($interest),
    //             'total_angsuran' => floatval($totalPayment),
    //             'baki_debet' => floatval($remainingBalance)
    //         ];

    //         $startDate = $paymentDate;
    //     }

    //     return $schedule;
    // }

    // private function insert_customer($request, $data, $cust_code)
    // {
    //     $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
    //     $cr_order = M_CrOrder::where('APPLICATION_ID', $data->ID)->first();
    //     $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();

    //     $getAttachment = DB::select(
    //         "   SELECT *
    //             FROM cr_survey_document AS csd
    //             WHERE (TYPE, TIMEMILISECOND) IN (
    //                 SELECT TYPE, MAX(TIMEMILISECOND)
    //                 FROM cr_survey_document
    //                 WHERE TYPE IN ('ktp', 'kk', 'ktp_pasangan')
    //                     AND CR_SURVEY_ID = '$data->CR_SURVEY_ID'
    //                 GROUP BY TYPE
    //             )
    //             ORDER BY TIMEMILISECOND DESC"
    //     );

    //     $data_customer = [
    //         'NAME' => $cr_personal->NAME ?? null,
    //         'ALIAS' => $cr_personal->ALIAS ?? null,
    //         'GENDER' => $cr_personal->GENDER ?? null,
    //         'BIRTHPLACE' => $cr_personal->BIRTHPLACE ?? null,
    //         'BIRTHDATE' => $cr_personal->BIRTHDATE ?? null,
    //         'BLOOD_TYPE' => $cr_personal->BLOOD_TYPE ?? null,
    //         'MOTHER_NAME' => $cr_order->MOTHER_NAME ?? null,
    //         'NPWP' => $cr_order->NO_NPWP ?? null,
    //         'MARTIAL_STATUS' => $cr_personal->MARTIAL_STATUS ?? null,
    //         'MARTIAL_DATE' => $cr_personal->MARTIAL_DATE ?? null,
    //         'ID_TYPE' => $cr_personal->ID_TYPE ?? null,
    //         'ID_NUMBER' => $cr_personal->ID_NUMBER ?? null,
    //         'KK_NUMBER' => $cr_personal->KK ?? null,
    //         'ID_ISSUE_DATE' => $cr_personal->ID_ISSUE_DATE ?? null,
    //         'ID_VALID_DATE' => $cr_personal->ID_VALID_DATE ?? null,
    //         'ADDRESS' => $cr_personal->ADDRESS ?? null,
    //         'RT' => $cr_personal->RT ?? null,
    //         'RW' => $cr_personal->RW ?? null,
    //         'PROVINCE' => $cr_personal->PROVINCE ?? null,
    //         'CITY' => $cr_personal->CITY ?? null,
    //         'KELURAHAN' => $cr_personal->KELURAHAN ?? null,
    //         'KECAMATAN' => $cr_personal->KECAMATAN ?? null,
    //         'ZIP_CODE' => $cr_personal->ZIP_CODE ?? null,
    //         'KK' => $cr_personal->KK ?? null,
    //         'CITIZEN' => $cr_personal->CITIZEN ?? null,
    //         'INS_ADDRESS' => $cr_personal->INS_ADDRESS ?? null,
    //         'INS_RT' => $cr_personal->INS_RT ?? null,
    //         'INS_RW' => $cr_personal->INS_RW ?? null,
    //         'INS_PROVINCE' => $cr_personal->INS_PROVINCE ?? null,
    //         'INS_CITY' => $cr_personal->INS_CITY ?? null,
    //         'INS_KELURAHAN' => $cr_personal->INS_KELURAHAN ?? null,
    //         'INS_KECAMATAN' => $cr_personal->INS_KECAMATAN ?? null,
    //         'INS_ZIP_CODE' => $cr_personal->INS_ZIP_CODE ?? null,
    //         'OCCUPATION' => $cr_personal->OCCUPATION ?? null,
    //         'OCCUPATION_ON_ID' => $cr_personal->OCCUPATION_ON_ID ?? null,
    //         'INCOME' => $cr_order->INCOME_PERSONAL ?? null,
    //         'RELIGION' => $cr_personal->RELIGION ?? null,
    //         'EDUCATION' => $cr_personal->EDUCATION ?? null,
    //         'PROPERTY_STATUS' => $cr_personal->PROPERTY_STATUS ?? null,
    //         'PHONE_HOUSE' => $cr_personal->PHONE_HOUSE ?? null,
    //         'PHONE_PERSONAL' => $cr_personal->PHONE_PERSONAL ?? null,
    //         'PHONE_OFFICE' => $cr_personal->PHONE_OFFICE ?? null,
    //         'EXT_1' => $cr_personal->EXT_1 ?? null,
    //         'EXT_2' => $cr_personal->EXT_2 ?? null,
    //         'VERSION' => 1,
    //         'CREATE_DATE' => Carbon::now(),
    //         'CREATE_USER' => $request->user()->id ?? 'alex',
    //     ];

    //     if (!$check_customer_ktp) {
    //         $data_customer['ID'] = Uuid::uuid7()->toString();
    //         $data_customer['CUST_CODE'] = $cust_code;
    //         $last_id = M_Customer::create($data_customer);

    //         $this->createCustomerDocuments($last_id->ID, $getAttachment);
    //     } else {
    //         $check_customer_ktp->update($data_customer);

    //         $this->createCustomerDocuments($check_customer_ktp->ID, $getAttachment);
    //     }
    // }

    // private function createCustomerDocuments($customerId, $attachments)
    // {

    //     M_CustomerDocument::where('CUSTOMER_ID', $customerId)->delete();

    //     foreach ($attachments as $res) {
    //         $custmer_doc_data = [
    //             'CUSTOMER_ID' => $customerId,
    //             'TYPE' => $res->TYPE,
    //             'COUNTER_ID' => $res->COUNTER_ID,
    //             'PATH' => $res->PATH,
    //             'TIMESTAMP' => round(microtime(true) * 1000)
    //         ];

    //         M_CustomerDocument::create($custmer_doc_data);
    //     }
    // }

    // private function insert_customer_xtra($data, $cust_code)
    // {

    //     $cr_personal = M_CrPersonal::where('APPLICATION_ID', $data->ID)->first();
    //     $cr_personal_extra = M_CrPersonalExtra::where('APPLICATION_ID', $data->ID)->first();
    //     $cr_spouse = M_CrApplicationSpouse::where('APPLICATION_ID', $data->ID)->first();
    //     $check_customer_ktp = M_Customer::where('ID_NUMBER', $cr_personal->ID_NUMBER)->first();
    //     $cr_order = M_CrOrder::where('APPLICATION_ID', $data->ID)->first();
    //     $update = M_CustomerExtra::where('CUST_CODE', $check_customer_ktp->CUST_CODE)->first();


    //     $data_customer_xtra = [
    //         'OTHER_OCCUPATION_1' => $cr_personal_extra->OTHER_OCCUPATION_1 ?? null,
    //         'OTHER_OCCUPATION_2' => $cr_personal_extra->OTHER_OCCUPATION_2 ?? null,
    //         'SPOUSE_NAME' =>  $cr_spouse->NAME ?? null,
    //         'SPOUSE_BIRTHPLACE' =>  $cr_spouse->BIRTHPLACE ?? null,
    //         'SPOUSE_BIRTHDATE' =>  $cr_spouse->BIRTHDATE ?? null,
    //         'SPOUSE_ID_NUMBER' => $cr_spouse->NUMBER_IDENTITY ?? null,
    //         'SPOUSE_INCOME' => $cr_order->INCOME_SPOUSE ?? null,
    //         'SPOUSE_ADDRESS' => $cr_spouse->ADDRESS ?? null,
    //         'SPOUSE_OCCUPATION' => $cr_spouse->OCCUPATION ?? null,
    //         'SPOUSE_RT' => null,
    //         'SPOUSE_RW' => null,
    //         'SPOUSE_PROVINCE' => null,
    //         'SPOUSE_CITY' => null,
    //         'SPOUSE_KELURAHAN' => null,
    //         'SPOUSE_KECAMATAN' => null,
    //         'SPOUSE_ZIP_CODE' => null,
    //         'INS_ADDRESS' => null,
    //         'INS_RT' => null,
    //         'INS_RW' => null,
    //         'INS_PROVINCE' => null,
    //         'INS_CITY' => null,
    //         'INS_KELURAHAN' => null,
    //         'INS_KECAMATAN' => null,
    //         'INS_ZIP_CODE' => null,
    //         'EMERGENCY_NAME' => $cr_personal_extra->EMERGENCY_NAME ?? null,
    //         'EMERGENCY_ADDRESS' => $cr_personal_extra->EMERGENCY_ADDRESS ?? null,
    //         'EMERGENCY_RT' => $cr_personal_extra->EMERGENCY_RT ?? null,
    //         'EMERGENCY_RW' => $cr_personal_extra->EMERGENCY_RW ?? null,
    //         'EMERGENCY_PROVINCE' => $cr_personal_extra->EMERGENCY_PROVINCE ?? null,
    //         'EMERGENCYL_CITY' => $cr_personal_extra->EMERGENCY_CITY ?? null,
    //         'EMERGENCY_KELURAHAN' => $cr_personal_extra->EMERGENCY_KELURAHAN ?? null,
    //         'EMERGENCYL_KECAMATAN' => $cr_personal_extra->EMERGENCY_KECAMATAN ?? null,
    //         'EMERGENCY_ZIP_CODE' => $cr_personal_extra->EMERGENCY_ZIP_CODE ?? null,
    //         'EMERGENCY_PHONE_HOUSE' => $cr_personal_extra->EMERGENCY_PHONE_HOUSE ?? null,
    //         'EMERGENCY_PHONE_PERSONAL' => $cr_personal_extra->EMERGENCY_PHONE_PERSONAL ?? null
    //     ];

    //     if (!$update) {
    //         $data_customer_xtra['ID'] = Uuid::uuid7()->toString();
    //         $data_customer_xtra['CUST_CODE'] =  $cust_code;
    //         M_CustomerExtra::create($data_customer_xtra);
    //     } else {
    //         $update->update($data_customer_xtra);
    //     }
    // }

    // private function insert_collateral($request, $data, $lastID, $loan_number)
    // {
    //     $data_collateral = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $data->CR_SURVEY_ID)->where(function ($query) {
    //         $query->whereNull('DELETED_AT')
    //             ->orWhere('DELETED_AT', '');
    //     })->get();

    //     $doc = $this->attachment_guarante($data->CR_SURVEY_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'");

    //     $setHeaderID = '';
    //     foreach ($doc as $res) {
    //         $setHeaderID = $res->COUNTER_ID ?? '';
    //     }

    //     if ($data_collateral->isNotEmpty()) {
    //         foreach ($data_collateral as $res) {
    //             $data_jaminan = [
    //                 'HEADER_ID' => $setHeaderID,
    //                 'CR_CREDIT_ID' => $lastID ?? null,
    //                 'TYPE' => $res->TYPE ?? null,
    //                 'BRAND' => $res->BRAND ?? null,
    //                 'PRODUCTION_YEAR' => $res->PRODUCTION_YEAR ?? null,
    //                 'COLOR' => $res->COLOR ?? null,
    //                 'ON_BEHALF' => $res->ON_BEHALF ?? null,
    //                 'POLICE_NUMBER' => $res->POLICE_NUMBER ?? null,
    //                 'CHASIS_NUMBER' => $res->CHASIS_NUMBER ?? null,
    //                 'ENGINE_NUMBER' => $res->ENGINE_NUMBER ?? null,
    //                 'BPKB_NUMBER' => $res->BPKB_NUMBER ?? null,
    //                 'BPKB_ADDRESS' => $res->BPKB_ADDRESS ?? null,
    //                 'STNK_NUMBER' => $res->STNK_NUMBER ?? null,
    //                 'INVOICE_NUMBER' => $res->INVOICE_NUMBER ?? null,
    //                 'STNK_VALID_DATE' => $res->STNK_VALID_DATE ?? null,
    //                 'VALUE' => $res->VALUE ?? null,
    //                 'LOCATION_BRANCH' => $data->BRANCH,
    //                 'COLLATERAL_FLAG' => $data->BRANCH,
    //                 'VERSION' => 1,
    //                 'CREATE_DATE' => $this->timeNow,
    //                 'CREATE_BY' => '61',
    //             ];

    //             $execute = M_CrCollateral::create($data_jaminan);

    //             $statusLog = 'NEW ' . $loan_number ?? '';

    //             M_LocationStatus::create([
    //                 'TYPE' => 'kendaraan',
    //                 'COLLATERAL_ID' => $execute->ID,
    //                 'LOCATION' => $data->BRANCH,
    //                 'STATUS' => $statusLog,
    //                 'CREATE_BY' => '66',
    //                 'CREATED_AT' => now(),
    //             ]);

    //             foreach ($doc as $res) {
    //                 $custmer_doc_data = [
    //                     'COLLATERAL_ID' => $execute->ID,
    //                     'TYPE' => $res->TYPE,
    //                     'COUNTER_ID' => $res->COUNTER_ID,
    //                     'PATH' => $res->PATH
    //                 ];

    //                 M_CrCollateralDocument::create($custmer_doc_data);
    //             }
    //         }
    //     }
    // }

    // public function attachment_guarante($survey_id, $data)
    // {
    //     $documents = DB::select(
    //         "   SELECT *
    //             FROM cr_survey_document AS csd
    //             WHERE (TYPE, TIMEMILISECOND) IN (
    //                 SELECT TYPE, MAX(TIMEMILISECOND)
    //                 FROM cr_survey_document
    //                 WHERE TYPE IN ($data)
    //                     AND CR_SURVEY_ID = '$survey_id'
    //                 GROUP BY TYPE
    //             )
    //             ORDER BY TIMEMILISECOND DESC"
    //     );

    //     return $documents;
    // }

    public function job()
    {
        try {

            // $setDate = DB::raw('CURDATE()');
            $setDate = '2025-02-01';

            $query = DB::table('credit_schedule')
                ->where('PAYMENT_DATE', '<=', $setDate)
                ->where(function ($query) {
                    $query->whereNull('PAID_FLAG')
                        ->orWhere('PAID_FLAG', '=', '');
                })
                ->select('*')
                ->get();

            $arrearsData = [];
            foreach ($query as $result) {
                // $date = date('Y-m-d');
                $date = '2025-02-01';
                $daysDiff = (strtotime($date) - strtotime($result->PAYMENT_DATE)) / (60 * 60 * 24);
                $pastDuePenalty = $result->INSTALLMENT * ($daysDiff * 0.005);

                $arrearsData[] = [
                    'ID' => Uuid::uuid7()->toString(),
                    'STATUS_REC' => 'A',
                    'LOAN_NUMBER' => $result->LOAN_NUMBER,
                    'START_DATE' => $result->PAYMENT_DATE,
                    'END_DATE' => null,
                    'PAST_DUE_PCPL' => $result->PRINCIPAL ?? 0,
                    'PAST_DUE_INTRST' => $result->INTEREST ?? 0,
                    'PAST_DUE_PENALTY' => $pastDuePenalty ?? 0,
                    'PAID_PCPL' => 0,
                    'PAID_INT' => 0,
                    'PAID_PENALTY' => 0,
                    'CREATED_AT' => Carbon::now('Asia/Jakarta')
                ];
            }

            foreach ($arrearsData as $data) {
                $existingArrears = M_Arrears::where([
                    'LOAN_NUMBER' => $data['LOAN_NUMBER'],
                    'START_DATE' => $data['START_DATE'],
                    'STATUS_REC' => 'A'
                ])->first();

                if ($existingArrears) {
                    // Update the existing record
                    $existingArrears->update([
                        'PAST_DUE_PENALTY' => $data['PAST_DUE_PENALTY'] ?? 0,
                        'UPDATED_AT' => Carbon::now('Asia/Jakarta')
                    ]);
                } else {
                    // Insert new record
                    M_Arrears::create($data);
                }
            }

            return response()->json('ok', 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}

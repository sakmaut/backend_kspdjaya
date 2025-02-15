<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\StatusApproval;
use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrPersonal;
use App\Models\M_CrProspect;
use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_FirstArr;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Models\M_TelegramBotSend;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

class Welcome extends Controller
{
    public function index(Request $request)
    {

        $data = M_CrApplication::where('ORDER_NUMBER', $request->order_number)->first();

        $set_tgl_awal = $request->tgl_awal;

        $type = $data->INSTALLMENT_TYPE;

        if (strtolower($type) == 'bulanan') {
            $data_credit_schedule = $this->generateAmortizationSchedule($set_tgl_awal, $data);

            $installment_count = count($data_credit_schedule);
        } else {
            $data_credit_schedule = $this->generateAmortizationScheduleMusiman($set_tgl_awal, $data);

            $installment_count = count($data_credit_schedule);
        }

        return response()->json("MUACHHHHHHHHHHHHHH");
        die;

        $groupedData = [];

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
            $byr_angsuran = $res['details'][0]['bayar_angsuran'];

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
}

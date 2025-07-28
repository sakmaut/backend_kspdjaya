<?php

namespace App\Http\Resources;

use App\Models\M_BpkbDetail;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_Kwitansi;
use App\Models\M_Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_PaymentCancelLog extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentCheck = DB::table('payment as a')
                            ->leftJoin('payment_detail as b', 'b.PAYMENT_ID', '=', 'a.ID')
                            ->select('a.LOAN_NUM', 'a.START_DATE', 'a.ORIGINAL_AMOUNT', 'b.ACC_KEYS', 'b.ORIGINAL_AMOUNT as amount')
                            ->where('a.INVOICE', $this->INVOICE_NUMBER)
                            ->get();

        $totals = [
            'principal' => 0,
            'interest' => 0,
            'penalty' => 0,
            'paidPenalty' => 0,
        ];
        $creditSchedule = [];

        if ($paymentCheck->isNotEmpty()) {
            $payment = M_Payment::where('INVOICE', $request->no_invoice)->update(['STTS_RCRD' => 'CANCEL']);

            foreach ($paymentCheck as $payment) {

                if (!isset($creditSchedule[$payment->START_DATE])) {
                    $creditSchedule[$payment->START_DATE] = [
                        'LOAN_NUMBER' => $payment->LOAN_NUM,
                        'PAYMENT_DATE' => date('Y-m-d', strtotime($payment->START_DATE)),
                        'AMOUNT' => 0,
                        'PRINCIPAL' => 0,
                        'INTEREST' => 0,
                        'PENALTY' => 0,
                        'PAID_PENALTY' => 0,
                    ];
                }

                $amount = floatval($payment->amount);
                switch ($payment->ACC_KEYS) {
                    case 'ANGSURAN_POKOK':
                        $creditSchedule[$payment->START_DATE]['PRINCIPAL'] += $amount;
                        break;
                    case 'ANGSURAN_BUNGA':
                        $creditSchedule[$payment->START_DATE]['INTEREST'] += $amount;
                        break;
                    case 'BAYAR_DENDA':
                        $creditSchedule[$payment->START_DATE]['PAID_PENALTY'] += $amount;
                        $creditSchedule[$payment->START_DATE]['PENALTY'] += $amount;
                        break;
                    case 'DISKON_DENDA':
                        $creditSchedule[$payment->START_DATE]['PENALTY'] += $amount;
                        break;
                }

                $creditSchedule[$payment->START_DATE]['AMOUNT'] =
                $creditSchedule[$payment->START_DATE]['PRINCIPAL'] +
                $creditSchedule[$payment->START_DATE]['INTEREST'];
            }

            // Calculate totals
            foreach ($creditSchedule as $schedule) {
                $totals['principal'] += $schedule['PRINCIPAL'];
                $totals['interest'] += $schedule['INTEREST'];
                $totals['penalty'] += $schedule['PENALTY'];
                $totals['paidPenalty'] += $schedule['PAID_PENALTY'];
            }
        }

        return [
                "id" => $this->ID,
                "no_invoice" => $this->INVOICE_NUMBER ?? null,
                "tgl_transaksi" => $this->TGL_TRANSAKSI ?? null,
                "request_by" => User::find($this->REQUEST_BY)->fullname ?? null,
                "request_branch" => M_Branch::find($this->REQUEST_BRANCH)->NAME ?? null,
                "request_position" => $this->REQUEST_POSITION ?? null,
                "request_date" => $this->REQUEST_DATE ?? null,
                "request_descr" => $this->REQUEST_DESCR ?? null,
                "oncharge_person" => User::find($this->ONCHARGE_PERSON)->fullname ?? null,
                "oncharge_time" => $this->ONCHARGE_TIME ?? null,
                "oncharge_descr" => $this->ONCHARGE_DESCR ?? null,
                "oncharge_flag" => $this->ONCHARGE_FLAG ?? null,
                'value' => [
                    'loan_number' => $this->LOAN_NUMBER,
                    'totalPrincipal' => $totals['principal'],
                    'totalInterest' => $totals['interest'],
                    'totalPenalty' => $totals['penalty'],
                    'totalPaidPenalty' => $totals['paidPenalty'],
                    'creditSchedule' => $creditSchedule,
                ],
            ];
    }
}

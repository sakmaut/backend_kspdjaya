<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class Rs_LpkDetail extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalBayar = $this->whenLoaded('payments', function () {
            return $this->payments
                ->flatMap(fn($payment) => $payment->details)
                ->sum('ORIGINAL_AMOUNT');
        }, 0);

        $log = $this->surveyLogs ?? "";

        return [
            'id' => $this->ID,
            'no_surat' => $this->NO_SURAT ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->LOAN_HOLDER ?? "",
            'desa' => $this->DESA ?? "",
            'kec' => $this->KEC ?? "",
            'tgl_jatuh_tempo' => $this->DUE_DATE ?? "",
            'tgl_jb' => $log->CONFIRM_DATE ?? "",
            'cycle_awal' => $this->CYCLE ?? "",
            'angusran_ke' => $this->INST_COUNT ?? "",
            'angsuran' => (int)($this->INSTALLMENT ?? 0),
            'mcf' => $this->petugas ?? "",
            'bayar' => $totalBayar->total_bayar ?? 0,
            'hasil_kunjungan' => $log->DESCRIPTION ?? "",
            'ambc_total' => (int)($this->deploy->AMBC_TOTAL_AWAL ?? 0),
        ];
    }
}

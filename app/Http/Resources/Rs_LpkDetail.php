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
        $totalBayarRow = DB::table('payment as p')
            ->leftJoin('payment_detail as pd', 'pd.PAYMENT_ID', '=', 'p.ID')
            ->where('LOAN_NUM', $this->LOAN_NUMBER)
            ->whereIn('pd.ACC_KEYS', ['BAYAR_POKOK', 'BAYAR_BUNGA', 'ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])
            ->whereMonth('p.ENTRY_DATE', now()->month)
            ->whereYear('p.ENTRY_DATE', now()->year)
            ->selectRaw('SUM(pd.ORIGINAL_AMOUNT) AS total_bayar, p.LOAN_NUM')
            ->groupBy('p.LOAN_NUM')
            ->first();

        $log = DB::table('cl_survey_logs')
            ->select('DESCRIPTION')
            ->where('REFERENCE_ID', $this->NO_SURAT)
            ->orderBy('CREATED_AT', 'desc')
            ->first();

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
            'bayar' => $totalBayarRow->total_bayar ?? 0,
            'hasil_kunjungan' => $log->DESCRIPTION ?? "",
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_LpkDetail extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ID,
            'no_surat' => $this->NO_SURAT ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->LOAN_HOLDER ?? "",
            'desa' => $this->DESA ?? "",
            'kec' => $this->KEC ?? "",
            'tgl_jatuh_tempo' => $this->DUE_DATE ?? "",
            'cycle_awal' => $this->CYCLE ?? "",
            'angusran_ke' => $this->INST_COUNT ?? "",
            'angsuran' => number_format((float)($this->PRINCIPAL + $this->INTEREST ?? 0), 0, '.', ','),
            'bayar' => "",
            "hasil_kunjungan" => "",
        ];
    }
}

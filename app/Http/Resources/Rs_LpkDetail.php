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
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->LOAN_HOLDER ?? "",
            'desa' => "",
            'kec' => "",
            'tgl_jatuh_tempo' => $this->DUE_DATE ?? "",
            'cycle_awal' => $this->CYCLE ?? "",
            'angusran_ke' => $this->INST_COUNT ?? "",
            'angsuran' => number_format((float) $this->PRINCIPAL + (float) $this->INTEREST ?? 0, '.', ','),
            'bayar' => "",
            "hasil_kunjungan" => "",
        ];
    }
}

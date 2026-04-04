<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_LkpPicList extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ID,
            'nama_pic' => $this->assignUser->fullname ?? "",
            'no_surat' => $this->NO_SURAT ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->customer->NAME ?? "",
            'cycle_awal' => $this->CYCLE_AWAL ?? "",
            'nbot' => $this->N_BOT ?? "",
            'alamat' => $this->customer->INS_ADDRESS ?? "",
            'desa' => $this->customer->INS_KELURAHAN ?? "",
            'kec' => $this->customer->INS_KECAMATAN ?? "",
            'mcf' => $this->MCF ?? "",
            'angsuran_ke' => $this->ANGSURAN_KE ?? 0,
            'tgl_jatuh_tempo' => $this->TGL_JTH_TEMPO ?? "",
            'tgl_jb' => $this->CONFIRM_DATE ?? "",
            'angsuran' => $this->ANGSURAN ?? 0,
            'total_angsuran' => $this->AMBC_TOTAL_AWAL ?? 0,
            'bayar' => (float) ($this->total_bayar ?? 0),
            'hasil_kunjungan' => $this->DESCRIPTION ?? "",
            'IsActive' => false
        ];
    }
}

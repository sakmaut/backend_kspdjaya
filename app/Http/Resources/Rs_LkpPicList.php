<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_LkpPicList extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userName = User::where('username', $this->USER_ID)->first();

        return [
            'id' => $this->ID,
            'nama_pic' =>  $userName->fullname ?? "",
            'no_surat' => $this->NO_SURAT ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->NAMA_CUST ?? "",
            'cycle_awal' => $this->CYCLE_AWAL ?? "",
            'nbot' => $this->N_BOT ?? "",
            'alamat' => $this->ALAMAT ?? "",
            'desa' => $this->DESA ?? "",
            'kec' => $this->KEC ?? "",
            'mcf' => $this->MCF ?? "",
            'angsuran_ke' => $this->ANGSURAN_KE ?? 0,
            'tgl_jatuh_tempo' => $this->TGL_JTH_TEMPO ?? "",
            'tgl_jb' => $this->CONFIRM_DATE ?? "",
            'angsuran' => $this->ANGSURAN ?? 0,
            'bayar' => (float) $this->total_bayar ?? 0,
            'hasil_kunjungan' => $this->DESCRIPTION ?? "",
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\M_CrCollateralDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_CollectorList extends JsonResource
{
    public function toArray(Request $request): array
    {
        $path = json_decode($this->PATH, true) ?? [];
        
        $kunjunganTerakhir = [
            'hasil_kunjungan' => $this->DESCRIPTION ?? "",
            'tgl_kunjungan' => $this->SURVEY_DATE
                ? Carbon::parse($this->SURVEY_DATE)->format('Y-m-d')
                : null,
            'tgl_jb' => $this->CONFIRM_DATE ?? null,
            'path' => $path
        ];

        return [
            'id' => $this->ID,
            'nama_pic' =>  $this->pic ?? "",
            'cabang' =>  $this->nama_cabang ?? "",
            'no_surat' => $this->NO_SURAT ?? "",
            'no_lkp' => $this->LKP_NUMBER ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->NAMA_CUST ?? $this->NAME ?? "",
            'alamat' => $this->ALAMAT ?? $this->INS_ADDRESS ?? "",
            'angusran_ke' => $this->ANGSURAN_KE ?? 0,
            'tgl_jatuh_tempo' => $this->TGL_JTH_TEMPO ?? "",
            'angsuran' => $this->ANGSURAN ?? 0,
            'cycle_awal' => $this->CYCLE_AWAL ?? null,
            'cycle_akhir' => $this->CYCLE_AKHIR ?? null,
            'kunjungan_terakhir' => collect($kunjunganTerakhir)->filter()->isEmpty() ? null : $kunjunganTerakhir,
            'pembayaran' => [
                'tgl_bayar' => $this->ENTRY_DATE ? Carbon::parse($this->ENTRY_DATE)->format('Y-m-d') : null,
                'total_bayar' => $this->total_bayar ?? 0
            ]
        ];
    }
}

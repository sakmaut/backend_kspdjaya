<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CrCollateral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'loan_number' => $this->credit->LOAN_NUMBER,
            'id' => $this->ID,
            "merk" => $this->BRAND,
            "tipe" => $this->TYPE,
            "tahun" => $this->PRODUCTION_YEAR,
            "warna" => $this->COLOR,
            "atas_nama" => $this->ON_BEHALF,
            "no_polisi" => $this->POLICE_NUMBER,
            "no_mesin" => $this->ENGINE_NUMBER,
            "no_rangka" => $this->CHASIS_NUMBER,
            'alamat_bpkb' => $this->BPKB_ADDRESS,
            "no_bpkb" => $this->BPKB_NUMBER,
            "no_stnk" => $this->STNK_NUMBER,
            'no_faktur' => $this->INVOICE_NUMBER,
            "tgl_stnk" => $this->STNK_VALID_DATE,
            "nilai" => intval($this->VALUE),
            "asal_lokasi" => $this->originBranch->NAME ?? '',
            "lokasi" => $this->currentBranch->NAME ?? '',
        ];
    }
}

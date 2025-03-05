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
            'id' => $this->ID,
            "tipe" => $this->TYPE,
            "merk" => $this->BRAND,
            "tahun" => $this->PRODUCTION_YEAR,
            "warna" => $this->COLOR,
            "atas_nama" => $this->ON_BEHALF,
            "no_polisi" => $this->POLICE_NUMBER,
            "no_rangka" => $this->CHASIS_NUMBER,
            "no_mesin" => $this->ENGINE_NUMBER,
            "no_bpkb" => $this->BPKB_NUMBER,
            "no_stnk" => $this->STNK_NUMBER,
            "tgl_stnk" => $this->STNK_VALID_DATE,
            "nilai" => (int) $this->VALUE,
            "asal_lokasi" => M_Branch::find($this->COLLATERAL_FLAG)->NAME ?? null,
            "lokasi" => M_Branch::find($this->LOCATION_BRANCH)->NAME ?? $this->LOCATION_BRANCH,
        ];
    }
}

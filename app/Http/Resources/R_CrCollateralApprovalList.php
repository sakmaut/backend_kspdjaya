<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CrCollateralApprovalList extends JsonResource
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
            "atas_nama" => $this->cr_collateral->ON_BEHALF,
            "no_polisi" => $this->cr_collateral->POLICE_NUMBER,
            "no_mesin" => $this->cr_collateral->ENGINE_NUMBER,
            "no_rangka" => $this->cr_collateral->CHASIS_NUMBER,
            'alamat_bpkb' => $this->cr_collateral->BPKB_ADDRESS,
            "no_bpkb" => $this->cr_collateral->BPKB_NUMBER,
            "no_stnk" => $this->cr_collateral->STNK_NUMBER,
            'no_faktur' => $this->cr_collateral->INVOICE_NUMBER,
            "tgl_stnk" => $this->cr_collateral->STNK_VALID_DATE,
            "after" => [
                "atas_nama" => $this->ON_BEHALF,
                "no_polisi" => $this->POLICE_NUMBER,
                "no_mesin" => $this->ENGINE_NUMBER,
                "no_rangka" => $this->CHASIS_NUMBER,
                'alamat_bpkb' => $this->BPKB_ADDRESS,
                "no_bpkb" => $this->BPKB_NUMBER,
                "no_stnk" => $this->STNK_NUMBER,
                'no_faktur' => $this->INVOICE_NUMBER,
                "tgl_stnk" => $this->STNK_VALID_DATE,
            ],
            "keterangan" => $this->DESCRIPTION,
            "dari" => $this->user->fullname,
            "cabang" => $this->branch->NAME,
            "posisi" => $this->user->position,
            "tanggal" => $this->REQUEST_AT
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Bpkb extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "no_polisi" => $this->POLICE_NUMBER,
            "no_rangka" => $this->CHASIS_NUMBER,
            "no_mesin" => $this->ENGINE_NUMBER,
            "no_bpkb" => $this->BPKB_NUMBER,
            "no_stnk" => $this->STNK_NUMBER,
        ];
    }
}

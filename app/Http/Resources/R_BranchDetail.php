<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_BranchDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ID' => $this->ID,
            'CODE' => $this->CODE,
            'NAME' => $this->NAME,
            'ADDRESS' => $this->ADDRESS,
            'RT' => $this->RT,
            'RW' => $this->RW,
            'PROVINCE' => $this->PROVINCE,
            'CITY' => $this->CITY,
            'KELURAHAN' => $this->KELURAHAN,
            'KECAMATAN' => $this->KECAMATAN,
            'ZIP_CODE' => $this->ZIP_CODE,
            'LOCATION' => $this->LOCATION,
            'PHONE_1' => $this->PHONE_1,
            'PHONE_2' => $this->PHONE_2,
            'PHONE_3' => $this->PHONE_3,
            'DESCR' =>$this->DESCR
        ];
    }
}

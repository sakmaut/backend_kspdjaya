<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_PayemtnDump extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'NO_LOAN' => $this->LOAN_NUM,
            'TANGGAL' => date("d-m-Y",strtotime($this->VALUE_DATE)),
            'TITLE' => $this->TITLE,
            'NOMINAL' => $this->ORIGINAL_AMOUNT/100,
            'OUTSTANDING' => $this->OS_PCPL_AMOUNT/100,
            'REF_NUM' => $this->REF_NUM,
            'ACC_KEYS' => $this->ACC_KEYS
        ];
    }
}

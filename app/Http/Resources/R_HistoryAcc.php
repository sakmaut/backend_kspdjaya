<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_HistoryAcc extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ACC_NUMBER' => $this->ACC_NUMBER,
            'VALUE_DATE' => date("d-m-Y",strtotime($this->VALUE_DATE)),
            'ENTRY_TIME' => $this->ENTRY_TIME,
            'TRANS_DESC' => $this->TRANS_DESC,
            'AMOUNT' => $this->AMOUNT,
            'NO_REF' => $this->NO_REF
        ];
    }
}

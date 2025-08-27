<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_TagihanByUserId extends JsonResource
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
            'no_surat' => $this->NO_SURAT,
            'loan_number' => $this->LOAN_NUMBER,
            'tgl_jth_tempo' => $this->tagihan_detail ?? [],
            'nama_customer' => $this->NAMA_CUST,
            'alamat' => $this->ALAMAT
        ];
    }
}

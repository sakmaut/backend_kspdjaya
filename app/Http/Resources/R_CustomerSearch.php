<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CustomerSearch extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "loan_number" => $this->LOAN_NUMBER,
            "no_kontrak" => $this->ORDER_NUMBER,
            "nama" => $this->NAME,
            "no_polisi" => $this->POLICE_NUMBER,
            "alamat" => $this->ADDRESS,
            "angsuran" => $this->INSTALLMENT
        ];
    }
}

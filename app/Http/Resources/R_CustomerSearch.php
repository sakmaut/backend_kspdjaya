<?php

namespace App\Http\Resources;

use App\Models\M_CreditSchedule;
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
            "loan_number" => $this->LOAN_NUMBER??null,
            "no_kontrak" => $this->ORDER_NUMBER??null,
            "nama" => $this->NAME??null,
            "no_polisi" => $this->POLICE_NUMBER?? null,
            "alamat" => $this->ADDRESS,
            "angsuran" => intval($this->INSTALLMENT??null)
        ];
    }
}

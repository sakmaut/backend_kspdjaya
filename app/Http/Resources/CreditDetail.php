<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "no_loan" => $this->LOAN_NUMBER ?? "",
            "loan_holder" => $this->customer->NAME ?? "",
            "angsuran" => number_format($this->INSTALLMENT ?? 0, 0, ',', '.'),
            "tenor" => $this->PERIOD ?? 0,
            "alamat" => $this->customer->ADDRESS ?? "",
            "tipe_kredit" => $this->CREDIT_TYPE ?? ""
        ];
    }
}

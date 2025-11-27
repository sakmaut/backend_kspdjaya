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
            "loan_number" => $this->LOAN_NUMBER ?? null,
            "no_kontrak" => $this->ORDER_NUMBER ?? null,
            "nama" => $this->NAME ?? null,
            "alamat" => $this->ADDRESS,
            "nilai_pinjaman" => intval($this->PCPL_ORI ?? 0),
            "tunggakan" => intval(0),
            "sisa_pokok" => (int) ($this->PAID_PRINCIPAL ?? 0),
            "angsuran" => (int) ($this->INSTALLMENT ?? 0),
        ];
    }
}

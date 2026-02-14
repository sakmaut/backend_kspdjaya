<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_CustomerSearch extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'loan_number'   => $this->LOAN_NUMBER,
            'no_kontrak'  => $this->ORDER_NUMBER,
            'nama' => $this->customer?->NAME ?? null,
            'alamat' => $this->customer?->ADDRESS ?? null,
            'police_number' => $this->collateral?->POLICE_NUMBER ?? null,
            'angsuran'   => intval($this->INSTALLMENT ?? 0)
        ];
    }
}

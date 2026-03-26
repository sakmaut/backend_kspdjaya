<?php

namespace App\Http\Credit\BungaMenurunFee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_BungaMenurunFee extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'Id' => $this->ID,
            'Plafond' => $this->LOAN_AMOUNT ?? 0,
            'Bunga' => $this->INTEREST_PERCENTAGE ?? 0,
            'Angsuran' => $this->INSTALLMENT ?? 0,
            'BiayaAdmin' => $this->ADMIN_FEE ?? 0,
            'BiayaBunga' => $this->INTEREST_FEE ?? 0,
            'BiayaProses' => $this->PROCCESS_FEE ?? 0,
            'Dibuat' => $this->CREATED_BY ?? 0,
            'TglBuat' => $this->CREATED_AT ?? 0
        ];
    }
}

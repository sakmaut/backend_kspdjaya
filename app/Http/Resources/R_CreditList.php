<?php

namespace App\Http\Resources;

use App\Models\M_CreditSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CreditList extends JsonResource
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
            'loan_number' => $this->LOAN_NUMBER,
            'cust_code' => $this->CUST_CODE,
            'order_number' => $this->ORDER_NUMBER,
            'sisa_angsuran' =>($this->PCPL_ORI - $this->PAID_PRINCIPAL),
            'total_bayar' => ($this->PAID_PRINCIPAL + $this->PAID_INTEREST),
        ];
    }
}

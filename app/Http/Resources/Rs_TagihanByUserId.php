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
            'detail' => $this->tagihan_detail
                ? $this->tagihan_detail->map(function ($item) {
                    return [
                        'angs_ke' => $item['INSTALLMENT_COUNT'] ?? '',
                        'tgl_jth_tempo' => $item['PAYMENT_DATE'] ?? null,
                        'jumlah' => $item['INSTALLMENT'] ?? 0,
                    ];
                })->values()->toArray()
                : [],
            'nama_customer' => $this->NAMA_CUST,
            'alamat' => $this->ALAMAT,
        ];
    }
}

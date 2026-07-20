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
            'alamat' => trim(
                ($this->customer?->ADDRESS ?? '') .
                    (!empty($this->customer?->RT) ? ' RT ' . str_pad($this->customer->RT, 3, '0', STR_PAD_LEFT) : '') .
                    (!empty($this->customer?->RW) ? ' RW ' . str_pad($this->customer->RW, 3, '0', STR_PAD_LEFT) : '') .
                    (!empty($this->customer?->KELURAHAN) ? ' Kel. ' . $this->customer->KELURAHAN : '') .
                    (!empty($this->customer?->KECAMATAN) ? ' Kec. ' . $this->customer->KECAMATAN : '') .
                    (!empty($this->customer?->CITY) ? ' ' . $this->customer->CITY : '') .
                    (!empty($this->customer?->ZIP_CODE) ? ' Kode Pos ' . $this->customer->ZIP_CODE : '')
            ),
            'no_polisi' => $this->collateral?->POLICE_NUMBER ?? null,
            'angsuran'   => intval($this->INSTALLMENT ?? 0)
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_VisitReports extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'TglVisit' => $this->CREATED_AT ?? null,
            'Cabang' => $this->Cabang ?? null,
            'NamaMcf' => $this->fullname ?? null,
            'NamaNasabah'          => $this->NAME ?? null,
            'AlamatNasabah'        => $this->INS_ADDRESS ?? null,
            'TeleponNasabah'       => $this->PHONE_PERSONAL ?? null,
            'StatusNasabah'       => $this->category ?? "Baru",
            'SumberOrder'       => $this->REF_PELANGGAN,
            'Keterangan' => $this->DESCRIPTION ?? null,
            'PathFile' => $this->PATH ?? null,
        ];
    }
}

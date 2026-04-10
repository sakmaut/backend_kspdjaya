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

        $path = null;

        if ($this->PATH) {
            $decoded = json_decode($this->PATH, true);
            $path = $decoded[0] ?? $this->PATH;
        }

        return [
            'TglVisit' => $this->CREATED_AT ?? null,
            'NoKontrak' => $this->LOAN_NUMBER ?? null,
            'Cabang' => $this->Cabang ?? null,
            'NamaMcf' => $this->fullname ?? null,
            'NamaNasabah' => $this->NAME ?? null,
            'AlamatNasabah' => $this->INS_ADDRESS ?? null,
            'TeleponNasabah' => $this->PHONE_PERSONAL ?? null,
            'StatusNasabah' => $this->category ?? "Baru",
            'SumberOrder' => $this->REF_PELANGGAN,
            'Keterangan' => $this->DESCRIPTION ?? null,
            'CycleAwal' => $this->CYCLE_AWAL ?? null,
            'CycleAkhir' => $this->CYCLE_AKHIR ?? null,
            'PathFile' => $path,
        ];
    }
}

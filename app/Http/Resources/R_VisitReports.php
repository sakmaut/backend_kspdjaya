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
            'NoKontrak' => $this->LOAN_NUMBER ?? null,
            'NamaNasabah' => $this->NAME ?? null,
            'Alamat' => $this->INS_ADDRESS ?? null,
            'TglVisit' => $this->SURVEY_DATE ?? null,
            'NamaMcf' => $this->fullname ?? null,
            'TglJb' => $this->CONFIRM_DATE ?? null,
            'Keterangan' => $this->DESCRIPTION ?? null,
        ];
    }
}

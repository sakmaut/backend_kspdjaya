<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_SurveyVisum extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'Id' => $this->id,
            'Status' => $this->status_konsumen,
            'Nama' => $this->nama_konsumen,
            'Alamat' => $this->alamat_konsumen,
            'NoHandphone' => $this->no_handphone,
            'Status' => $this->status_konsumen,
            'HasilFollowup' => $this->hasil_followup,
            'SumberOrder' => $this->sumber_order,
            'Keterangan' => $this->keterangan,
            'Path' => $this->path,
            'NamaMcf' => $this->user->fullname ?? null,
            'Cabang' => $this->branch->NAME ?? null,
            'TanggalKunjungan' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s') ?? null,
        ];
    }
}

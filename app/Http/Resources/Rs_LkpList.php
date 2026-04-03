<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_LkpList extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->LKP_ID,
            'no_lkp' => $this->NoLKP ?? "",
            'petugas' => $this->NamaPetugas ?? "",
            'cabang' => $this->cabang ?? "",
            'tanggal' => Carbon::parse($this->Tanggal)->format('Y-m-d') ?? "",
            'jml_surat_tgh' => (int) $this->JumlahSurat ?? 0,
            'presentase' => (float) $this->Progres ?? 0,
            "status" => $this->Status ?? "",
        ];
    }
}

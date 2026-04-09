<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_LkpDetailList extends JsonResource
{
    public function toArray(Request $request): array
    {
        $petugas = $this->detail->first()->deploy->MCF ?? "";

        return [
            'id' => $this->ID,
            'no_lkp' => $this->LKP_NUMBER ?? "",
            'petugas' => $petugas,
            'tanggal' => Carbon::parse($this->CREATED_AT)->format('Y-m-d') ?? "",
            'jml_surat_tgh' => $this->NOA ?? 0,
            'details' => Rs_LpkDetail::collection(
                $this->whenLoaded('detail')->map(function ($item) use ($petugas) {
                    $item->petugas = $petugas;
                    return $item;
                })
            )
        ];
    }
}

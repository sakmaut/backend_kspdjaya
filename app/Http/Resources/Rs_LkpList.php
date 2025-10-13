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
        $userName = User::where('username', $this->USER_ID)->first();
        return [
            'id' => $this->ID,
            'no_lkp' => $this->LKP_NUMBER ?? "",
            'petugas' => $userName->fullname ?? "",
            'tanggal' => Carbon::parse($this->CREATED_AT)->format('Y-m-d') ?? "",
            'jml_surat_tgh' => $this->NOA ?? 0,
        ];
    }
}

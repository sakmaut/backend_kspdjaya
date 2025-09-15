<?php

namespace App\Http\Controllers\Saving\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_TransaksiLog extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "tgl_transaksi" => $this->TRX_DATE ?? '',
            "no_rek" => $this->savings->ACC_NUM ?? '',
            "pemilik" => $this->savings->customer->NAME ?? '',
            "nominal" => (float) $this->BALANCE ?? 0,
            "operator" => $this->user->fullname ?? '',
            "tipe" => $this->TRX_TYPE ?? '',
            "ket" => $this->DESCRIPTION ?? '',
        ];
    }
}

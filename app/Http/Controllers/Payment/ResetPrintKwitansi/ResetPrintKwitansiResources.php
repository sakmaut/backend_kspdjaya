<?php

namespace App\Http\Controllers\Payment\ResetPrintKwitansi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResetPrintKwitansiResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "NoKwitansi" => $this->ID,
            "JumlahPrint" => $this->COUNT ?? 0,
            "Keterangan" => $this->COUNT >= 3
                ? "Cetak Melebihi Batas"
                : "Dalam Batas Cetak"
        ];
    }
}

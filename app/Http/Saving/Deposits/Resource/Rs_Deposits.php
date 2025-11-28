<?php

namespace App\Http\Saving\Deposits\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_Deposits extends JsonResource
{
    public function toArray(Request $request): array
    {

        // bunga kotor sebelum pajak
        $bunga_kotor = $this->deposit_value * $this->int_rate * ($this->period / 12);

        // pajak (20% dari bunga kotor)
        $pajak = $bunga_kotor * 0.20;

        // bunga bersih yg diterima
        $bunga_bersih = $bunga_kotor - $pajak;

        return [
            "id" => $this->id,
            "no_deposito" => $this->deposit_number,
            "nama_pemilik" => $this->deposit_holder,
            "alamat" => "",
            "nominal" => (int) ($this->deposit_value ?? 0),
            "bunga" => (int) ($this->int_rate ?? 0),
            "pajak" => $bunga_kotor / 100,
            "bunga_pajak" => $bunga_bersih / 100,
            "periode" => $this->period,
        ];
    }
}

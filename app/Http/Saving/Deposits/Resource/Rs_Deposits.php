<?php

namespace App\Http\Saving\Deposits\Resource;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_Deposits extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current_date = Carbon::now();
        $day_calc = $current_date->diffInDays($this->created_at); // jumlah hari sejak dibuat

        // Hitung bunga kotor (bunga tahunan dibagi 365 hari)
        $bunga_kotor = $this->deposit_value * ($this->int_rate / 100) * ($day_calc / 365);

        // Pajak 20%
        $pajak = $bunga_kotor * 0.20;

        // Bunga bersih setelah pajak
        $bunga_bersih = $bunga_kotor - $pajak;

        return [
            "id" => $this->id,
            "no_deposito" => $this->deposit_number,
            "nama_pemilik" => $this->deposit_holder,
            "nominal" => (int) ($this->deposit_value ?? 0),
            "bunga" => (float) ($this->int_rate ?? 0),
            "nilai_bunga" => (int) $bunga_kotor,
            "pajak" => (int) $pajak,
            "bunga_pajak" => (int) $bunga_bersih,
            "hari_aktif" => $day_calc,
            "periode" => $this->period,
            "status" => $this->status,
            "tgl_mulai" => $this->created_at,
            "kalkulasi_tgl" => $this->day_calc,
        ];
    }
}

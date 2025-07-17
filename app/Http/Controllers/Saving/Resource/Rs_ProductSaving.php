<?php

namespace App\Http\Controllers\Saving\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_ProductSaving extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "nama_jenis" => $this->product_name,
            "kode_jenis" => $this->product_type,
            "bunga" => floatval($this->interest_rate),
            "minimal_saldo" => floatval($this->min_deposit),
            "biaya_administrasi" => floatval($this->admin_fee),
            "jangka_waktu" => intval($this->term_length),
            "deskripsi" => $this->description,
            "status" => $this->status
        ];
    }
}

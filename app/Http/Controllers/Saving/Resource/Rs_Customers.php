<?php

namespace App\Http\Controllers\Saving\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_Customers extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "nama_produk" => $this->product_name,
            "jenis_produk" => $this->product_type,
            "suku_bunga" => floatval($this->interest_rate),
            "setoran_minimum" => floatval($this->min_deposit),
            "biaya_administrasi" => floatval($this->admin_fee),
            "jangka_waktu" => intval($this->term_length)
        ];
    }
}

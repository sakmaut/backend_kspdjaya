<?php

namespace App\Http\Saving\Deposits\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_Deposits extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "no_deposito" => $this->deposit_number,
            "nama_pemilik" => $this->deposit_holder,
            "alamat" => ""
        ];
    }
}

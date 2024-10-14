<?php

namespace App\Http\Resources;

use App\Models\M_TaksasiPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Taksasi extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $taksasi_price = M_TaksasiPrice::where('taksasi_id', $this->id)
                                        ->select('year as name', 'price as harga')
                                        ->get();

        return [
            'id' => $this->id,
            "brand" => $this->brand,
            "code" => $this->code,
            "model" => $this->model,
            "descr" => $this->descr,
            'price' => $taksasi_price,
        ];
    }
}

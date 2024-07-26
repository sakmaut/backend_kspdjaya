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
        return [
            'data' => $this->getCollection()->map(function ($item) {
                $taksasi_price = M_TaksasiPrice::where('taksasi_id', $item->id)
                                ->select('year as name', 'price as harga')
                                ->get();
    
                return [
                    'id' => $item->id,
                    "brand" => $item->brand,
                    "code" => $item->code,
                    "model" => $item->model,
                    "descr" => $item->descr,
                    'price' => $taksasi_price,
                ];
            }),
            'eta' => [
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'total_records' => $this->total(),
                'per_page' => 20,
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }
}

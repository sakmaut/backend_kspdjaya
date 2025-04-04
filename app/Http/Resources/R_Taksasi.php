<?php

namespace App\Http\Resources;

use App\Models\M_TaksasiPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
                                        ->get()
                                        ->map(function ($item) {
                                            $item->name = (int) $item->name;
                                            $item->harga = (int) $item->harga;
                                            return $item;
                                        });

        $min_max_year = DB::table('taksasi_price')
                            ->where('taksasi_id', $this->id)
                            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
                            ->first();
                                    
        $min_year = $min_max_year->min_year;
        $max_year = $min_max_year->max_year;              

        return [
           'id' => $this->id,
            "brand" => $this->brand,
            "code" => $this->code,
            "model" => $this->model,
            "descr" => $this->descr,
            "dari" => (int)$min_year,
            "sampai" => (int)$max_year,
            'price' => $taksasi_price
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailDocument extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "no_kontrak" => $this->credit['LOAN_NUMBER'],
            "atas_nama" => $this->credit['customer']['NAME'],
            // "ktp" => $this->customer['NAME'],
            // kk: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // ktp_pasangan: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // no_rangka: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // no_mesin: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // stnk: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // depan: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // belakang: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // kanan: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // kiri: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
            // dok_pendukung: 'https://i.pinimg.com/474x/e7/ac/62/e7ac62da918dc5d72062953570bac97f.jpg',
        ];
    }
}

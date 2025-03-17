<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_PhoneBook extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ID ?? '',
            'cust_code' => $this->CUST_CODE ?? '',
            'nama' => $this->NAME ?? '',
            'alias' => $this->phone_book->ALIAS ?? '',
            'no_hp' => $this->phone_book->PHONE_NUMBER ?? ''
        ];
    }
}

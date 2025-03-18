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
        $phones = [];
        foreach ($this->phone_book as $list) {
            $data = [
                "PHONE_NUMBER" => $list->PHONE_NUMBER ?? '',
                'ALIAS' => $list->ALIAS,
                'CREATED_BY' => $list->user->fullname,
                'CREATED_AT' => $list->CREATED_AT,
            ];

            $phones[] = $data;
        }

        return [
            'id' => $this->ID ?? '',
            'cust_code' => $this->CUST_CODE ?? '',
            'nama' => $this->NAME ?? '',
            'phone' => $phones,
        ];
    }
}

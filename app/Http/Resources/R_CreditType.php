<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CreditType extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'codename' => $this->codename,
            'description' => $this->description,
            'terms' => $this->terms,
            'image' => $this->image_path == null ? '': url('/'.$this->image_path),
            'status' => $this->status
        ];
    }
}

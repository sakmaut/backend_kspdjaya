<?php
namespace App\Http\Credit\Blacklist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlacklistDTO extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'Id' => $this->id,
            'Category' => $this->category,
            "Value" => $this->value,
            "Status" => $this->status,
            "Note" => $this->reason,
            "CreatedAt" => $this->created_at
        ];
    }
}

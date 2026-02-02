<?php


namespace App\Http\Controllers\Ticketing;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketingDTO extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->id,
            'ticket_no' => $this->ticket_number,
            "title" => $this->title,
            "category" => $this->category,
            "priority" => $this->priority,
            "status" => $this->status,
            "keterangan" => $this->description,
            "assignee" => $this->current_assignee_id,
            "created_at" => $this->created_at
        ];
    }
}

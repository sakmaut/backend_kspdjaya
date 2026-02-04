<?php


namespace App\Http\Controllers\Ticketing\TicketingMessages;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketingMessagesDTO extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'Key' => $this->id,
            'TicketId' => $this->ticket_id,
            'Messages' => $this->messages,
            "Attach" => json_decode($this->file_path),
            "CreatedBy" => optional($this->currentUser)->fullname,
            "CreatedTime " => $this->created_at
        ];
    }
}

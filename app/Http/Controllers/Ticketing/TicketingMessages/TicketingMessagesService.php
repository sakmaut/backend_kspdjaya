<?php

namespace App\Http\Controllers\Ticketing\TicketingMessages;

use App\Http\Controllers\Ticketing\TicketingMessages\TicketingMessagesRepository;
use Exception;
use Illuminate\Support\Carbon;

class TicketingMessagesService extends TicketingMessagesRepository
{
    public function getDetailByTicketId($id)
    {
        return $this->findByTicketId($id);
    }

    public function create($request)
    {
        $user = $request->user();

        $messages = TicketingMessagesRepository::create([
            'ticket_id' => $request->TicketId ?? "",
            'messages'  => $request->Messages ?? "",
            'file_path' => json_encode($request->Attach) ?? [],
            'created_by' => $user->id,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return $messages;
    }
}

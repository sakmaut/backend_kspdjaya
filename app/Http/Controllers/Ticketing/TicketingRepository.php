<?php

namespace App\Http\Controllers\Ticketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingRepository extends TicketingEntity
{
    public function getAll()
    {
        return $this->query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(array $data)
    {
        return $this->create($data);
    }

    public function getById($id)
    {
        return $this->query()->findOrFail($id);
    }

    public function updateTicket(string $id, array $data)
    {
        $ticket = self::where('id', $id)->firstOrFail();

        $ticket->update([
            'category'    => $data['category'] ?? $ticket->category,
            'priority'    => $data['priority'] ?? $ticket->priority,
            'status'      => $data['status'] ?? $ticket->status,
            'description' => $data['description'] ?? $ticket->description,
        ]);

        return $ticket;
    }

    public function closeTicket(string $id)
    {
        return self::where('id', $id)->update([
            'status' => 'CLOSED'
        ]);
    }

    public function assignHandler(string $ticketId, string $userId)
    {
        return DB::transaction(function () use ($ticketId, $userId) {

            TicketingAssigmentEntity::where('ticket_id', $ticketId)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            TicketingAssigmentEntity::create([
                'ticket_id'   => $ticketId,
                'user_id'     => $userId,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);

            self::where('id', $ticketId)->update([
                'current_assignee_id' => $userId,
                'status' => 'IN_PROGRESS'
            ]);

            return true;
        });
    }
}

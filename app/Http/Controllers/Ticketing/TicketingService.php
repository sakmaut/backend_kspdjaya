<?php

namespace App\Http\Controllers\Ticketing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;

class TicketingService extends TicketingRepository
{
    public function getAllData()
    {
        return $this->getAll();
    }

    public function findById($id)
    {
        return $this->getById($id);
    }

    public function create($request)
    {
        $ticketNumber = generateTicketCode($request, 'tic_tickets', 'ticket_number');
        $userId = $request->user()->id;

        $ticket = self::store([
            'ticket_number' => $ticketNumber,
            'title'   => $request->title ?? "",
            'category'      => $request->category ?? "",
            'priority'      => $request->priority ?? "",
            'status'        => $request->status ?? "",
            'description'   => $request->keterangan ?? "",
            'created_by'    => $userId,
            'created_at'    => Carbon::now('Asia/Jakarta')
        ]);

        TicketingAssigmentEntity::create([
            'ticket_id'   => $ticket->id,
            'assigned_to' => null,
            'status'      => $request->status ?? "",
            'created_by'  => $userId,
            'created_at'  => Carbon::now('Asia/Jakarta'),
        ]);

        return $ticket;
    }

}

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
        $branchId = auth()->user()->branch_id;
        $userId   = auth()->user()->id;

        $ticketNumber = generateTicketCode(
            $branchId,
            'tic_tickets',
            'ticket_number'
        );

        $ticket = self::store([
            'ticket_number' => $ticketNumber,
            'title'   => $request->title ?? "",
            'category'      => $request->category ?? "",
            'priority'      => $request->priority ?? "",
            'status'        => $request->status ?? "",
            'description'   => $request->description ?? "",
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

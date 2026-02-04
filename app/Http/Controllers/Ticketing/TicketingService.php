<?php

namespace App\Http\Controllers\Ticketing;

use Exception;
use Illuminate\Support\Carbon;

class TicketingService extends TicketingRepository
{
    public function getAllData()
    {
        return $this->getAll();
    }

    public function getDetailById($id)
    {
        return $this->findById($id);
    }

    public function create($request)
    {
        $user = $request->user();

        $ticketNumber = generateTicketCode(
            $user->branch_id,
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
            'path_image'    => json_encode($request->lampiran) ?? [],
            'created_by'    => $user->id,
            'created_at'    => Carbon::now('Asia/Jakarta')
        ]);

        TicketingAssigmentEntity::create([
            'ticket_id'   => $ticket->id,
            'assigned_to' => null,
            'status'      => $request->status ?? "",
            'created_by'  => $user->id,
            'created_at'  => Carbon::now('Asia/Jakarta'),
        ]);

        return $ticket;
    }

    public function assignHandler($request, $id)
    {
        $user = $request->user();
        $userAssignId = $request->assign_id;
        $setStatus = 'Open';

        if (!$userAssignId) {
            throw new Exception('User assign tidak boleh kosong');
        }

        $ticket = $this->findById($id);

        if (!$ticket) {
            throw new Exception('Ticket tidak ditemukan');
        }

        if ($ticket->status === 'closed') {
            throw new Exception('Ticket sudah ditutup dan tidak bisa di-assign');
        }

        $ticket->current_assignee_id = $userAssignId;
        $ticket->status = $setStatus;
        $ticket->save();

        TicketingAssigmentEntity::Create(
            [
                'ticket_id' => $ticket->id,
                'assigned_to' => $userAssignId,
                'status'      => $setStatus,
                'created_by'  => $user->id,
                'created_at'  => Carbon::now('Asia/Jakarta'),
            ]
        );

        return $ticket->fresh();
    }

    public function updateToClosedTicket(string $ticketId, string $userId, bool $isClosed)
    {
        $ticket = $this->findById($ticketId);

        if (!$ticket) {
            throw new \Exception("Ticket tidak ditemukan");
        }

        $ticket->update(['is_closed' => $isClosed]);

        TicketingAssigmentEntity::create([
            'ticket_id'   => $ticketId,
            'status'      => "Request Closed",
            'created_by'  => $userId,
            'created_at'  => Carbon::now('Asia/Jakarta'),
        ]);

        return $ticket;
    }

    public function updateToClosedTicketByPic($request)
    {
        $ticket = $this->findById($request->TicketId);

        if (!$ticket) {
            throw new \Exception("Ticket tidak ditemukan");
        }

        $statusText = $request->isClosed ? "Ticket Closed" : "Reject Closed";

        if ($request->isClosed) {
            $ticket->update(['status' => 'Closed']);
        }

        TicketingAssigmentEntity::create([
            'ticket_id'  => $ticket->id,
            'status'     => $statusText,
            'created_by' => $request->user()->id,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return $ticket;
    }
}

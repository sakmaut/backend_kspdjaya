<?php

namespace App\Http\Controllers\Ticketing;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;

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
        $ticket->save();

        TicketingAssigmentEntity::Create(
            [
                'ticket_id' => $ticket->id,
                'assigned_to' => $userAssignId,
                'status'      => $request->status ?? $ticket->status,
                'created_by'  => $user->id,
                'created_at'  => Carbon::now('Asia/Jakarta'),
            ]
        );

        return $ticket->fresh();
    }
}

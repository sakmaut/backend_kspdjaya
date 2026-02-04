<?php

namespace App\Http\Controllers\Ticketing\TicketingMessages;

use App\Http\Controllers\Ticketing\TicketingMessages\TicketingMessagesRepository;
use App\Http\Controllers\Ticketing\TicketingService;
use Illuminate\Support\Carbon;

class TicketingMessagesService
{
    protected $ticketingService;
    protected $repository;

    public function __construct(
        TicketingService $ticketingService,
        TicketingMessagesRepository $repository
    ) {
        $this->ticketingService = $ticketingService;
        $this->repository = $repository;
    }

    public function getDetailByTicketId($id)
    {
        return $this->repository->findByTicketId($id);
    }

    public function create($request)
    {
        $user = $request->user();

        $messages = $this->repository->create([
            'ticket_id'  => $request->TicketId ?? "",
            'messages'   => $request->Messages ?? "",
            'file_path'  => json_encode($request->Attach) ?? [],
            'created_by' => $user->id,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $this->ticketingService->updateToClosedTicket(
            $request->TicketId,
            $user->id
        );

        return $messages;
    }
}

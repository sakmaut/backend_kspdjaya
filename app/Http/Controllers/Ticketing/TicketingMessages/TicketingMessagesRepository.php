<?php

namespace App\Http\Controllers\Ticketing\TicketingMessages;

class TicketingMessagesRepository extends TicketingMessagesEntity
{
    protected array $withRelations = [
        'currentUser:id,fullname',
    ];

    protected array $columns = [
        'id',
        'ticket_id',
        'messages',
        'file_path',
        'created_by',
        'created_at',
    ];

    protected function baseQuery()
    {
        return self::query()
            ->with($this->withRelations)
            ->select($this->columns);
    }

    public function getAll()
    {
        return $this->baseQuery()
            ->orderByDesc('created_at')
            ->get();
    }

    public function findByTicketId($id)
    {
        return $this->baseQuery()
            ->where('ticket_id',$id)
            ->get();
    }

    public function store(array $data)
    {
        return self::create($data);
    }
}

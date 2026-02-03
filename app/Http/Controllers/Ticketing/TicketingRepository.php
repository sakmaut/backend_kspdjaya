<?php

namespace App\Http\Controllers\Ticketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingRepository extends TicketingEntity
{
    public function getAll()
    {
        return self::query()
            ->with([
                'currentAssignee:id,fullname'
            ])
            ->select(
                'id',
                'ticket_number',
                'title',
                'category',
                'priority',
                'status',
                'description',
                'path_image',
                'current_assignee_id',
                'created_at'
            )
            ->orderBy('created_at', 'desc')
            ->get();
    }


    public function store(array $data)
    {
        return self::create($data);
    }

    public function findById($id)
    {
        return $this->with([
            'currentAssignee:id,fullname'
        ])->find($id);
    }
}

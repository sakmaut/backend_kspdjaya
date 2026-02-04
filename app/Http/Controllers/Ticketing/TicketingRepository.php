<?php

namespace App\Http\Controllers\Ticketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingRepository extends TicketingEntity
{
    protected array $withRelations = [
        'currentAssignee:id,fullname',
    ];

    protected array $columns = [
        'id',
        'ticket_number',
        'title',
        'category',
        'priority',
        'status',
        'description',
        'path_image',
        'current_assignee_id',
        'is_closed',
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

    public function findById($id)
    {
        return $this->baseQuery()
            ->whereKey($id)
            ->first();
    }

    public function store(array $data)
    {
        return self::create($data);
    }
}

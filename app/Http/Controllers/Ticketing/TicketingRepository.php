<?php

namespace App\Http\Controllers\Ticketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketingRepository extends TicketingEntity
{
    public function getAll()
    {
        return self::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(array $data)
    {
        return self::create($data);
    }

    public function getById($id)
    {
        return self::query()->find($id);
    }
}

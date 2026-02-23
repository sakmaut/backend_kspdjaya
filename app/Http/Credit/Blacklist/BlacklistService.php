<?php

namespace App\Http\Credit\Blacklist;

use App\Http\Controllers\Enum\Status;
use Illuminate\Support\Carbon;

class BlacklistService
{
    protected BlacklistRepository $repository;

    public function __construct(BlacklistRepository $repository)
    {
        $this->repository = $repository;
    }

    public function showAll()
    {
        return $this->repository->showAll();
    }

    public function findById($id)
    {
        return $this->repository->findById($id);
    }

    public function create($request)
    {
        $user = $request->user();

        $blacklist = $this->repository->store([
            'category'   => $request->input('Category', ''),
            'value'      => $request->input('Value', ''),
            'status'     => Status::ACTIVE,
            'reason'     => $request->input('Note', ''),
            'created_by' => $user->id,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $this->createHistory($blacklist->id, Status::ACTIVE, $request->input('Note', ''), $user->id);

        return $blacklist;
    }

    public function update($request, $id)
    {
        $user   = $request->user();
        $status = $request->boolean('Status') ? Status::ACTIVE : Status::INACTIVE;

        $blacklist = $this->repository->update($id, [
            'reason'     => $request->input('Note', ''),
            'status'     => $status,
        ]);

        $this->createHistory($id, $status, $request->input('Note', ''), $user->id);

        return $blacklist;
    }

    protected function createHistory(String $blacklistId, String $status, string $reason, String $userId)
    {
        return BlacklistHistoryEntity::create([
            'cr_blacklist_id' => $blacklistId,
            'status'          => $status,
            'reason'          => $reason,
            'created_by'      => $userId,
            'created_at'      => Carbon::now('Asia/Jakarta'),
        ]);
    }
}

<?php

namespace App\Http\Credit\Blacklist;

class BlacklistRepository
{
    protected BlacklistEntity $model;

    protected array $columns = [
        'id',
        'category',
        'value',
        'status',
        'reason',
        'created_by',
        'created_at'
    ];

    public function __construct(BlacklistEntity $model)
    {
        $this->model = $model;
    }

    protected function baseQuery()
    {
        return $this->model::query()->select($this->columns);
    }

    public function showAll()
    {
        $query = $this->baseQuery();

        return $query->orderByDesc('created_at')->get();
    }

    public function findById($id)
    {
        return $this->baseQuery()
            ->whereKey($id)
            ->first();
    }

    public function store(array $data)
    {
        return $this->model::create($data);
    }

    public function update(String $id, array $data)
    {
        $record = $this->model::findOrFail($id);
        $record->update($data);

        return $record;
    }
}

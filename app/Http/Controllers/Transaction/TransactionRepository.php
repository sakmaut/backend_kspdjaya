<?php

namespace App\Http\Controllers\Transaction;

class TransactionRepository
{
    protected TransactionModel $entity;

    public function __construct(TransactionModel $entity) {
        $this->entity = $entity;
    }

    public function create(array $request)
    {
        return $this->entity::create($request);
    } 
}

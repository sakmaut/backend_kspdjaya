<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Credit\Tagihan\Repository\R_Tagihan;

class S_Tagihan extends R_Tagihan
{
    protected $repository;

    public function __construct(R_Tagihan $repository)
    {
        $this->repository = $repository;
    }

    public function getListTagihan($request)
    {
        $sql = $this->repository->getAllListTagihan($request);
        return $sql;
    }

    public function createTagihan($fields)
    {
        return $this->repository->create($fields);
    }
}

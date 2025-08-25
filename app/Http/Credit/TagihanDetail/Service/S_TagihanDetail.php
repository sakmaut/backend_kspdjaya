<?php

namespace App\Http\Credit\TagihanDetail\Service;

use App\Http\Credit\TagihanDetail\Repository\R_TagihanDetail;

class S_TagihanDetail extends R_TagihanDetail
{
    protected $repository;

    public function __construct(R_TagihanDetail $repository)
    {
        $this->repository = $repository;
    }

    // TODO: Service methods here
}

<?php

namespace App\Http\Credit\TagihanDetail\Repository;

use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;

class R_TagihanDetail
{
    protected $model;

    public function __construct(M_TagihanDetail $model)
    {
        $this->model = $model;
    }

    // TODO: Repository methods here
}

<?php

namespace App\Http\Credit\TagihanDocument\Repository;

use App\Http\Credit\TagihanDocument\Model\M_TagihanDocument;

class R_TagihanDocument
{
    protected $model;

    public function __construct(M_TagihanDocument $model)
    {
        $this->model = $model;
    }

    // TODO: Repository methods here
}

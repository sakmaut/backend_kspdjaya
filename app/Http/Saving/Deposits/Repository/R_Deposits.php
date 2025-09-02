<?php

namespace App\Http\Saving\Deposits\Repository;

use App\Http\Saving\Deposits\Model\M_Deposits;

class R_Deposits
{
    protected $model;

    public function __construct(M_Deposits $model)
    {
        $this->model = $model;
    }

    protected function all()
    {
        return $this->model->all();
    }

    protected function findDepositoBy($deposit_number)
    {
        return $this->model->where('deposit_number', $deposit_number)->first();
    }

    protected function create($fields)
    {
        return $this->model->create($fields);
    }
}

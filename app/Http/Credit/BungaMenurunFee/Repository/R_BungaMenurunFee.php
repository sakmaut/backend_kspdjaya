<?php

namespace App\Http\Credit\BungaMenurunFee\Repository;

use App\Http\Credit\BungaMenurunFee\Model\M_BungaMenurunFee;

class R_BungaMenurunFee
{
    protected $model;

    public function __construct(M_BungaMenurunFee $model)
    {
        $this->model = $model;
    }

    protected function findFeeByLoanAmount($loanAmount)
    {
        return $this->model->where('LOAN_AMOUNT', $loanAmount)->first();
    }
}

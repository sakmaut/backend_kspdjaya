<?php

namespace App\Http\Credit\BungaMenurunFee\Service;

use App\Http\Credit\BungaMenurunFee\Repository\R_BungaMenurunFee;

class S_BungaMenurunFee extends R_BungaMenurunFee
{
    protected $repository;

    public function __construct(R_BungaMenurunFee $repository)
    {
        $this->repository = $repository;
    }

    public function getFeeByLoanAmount($loanAmount)
    {
        return $this->repository->findFeeByLoanAmount($loanAmount);
    }
}

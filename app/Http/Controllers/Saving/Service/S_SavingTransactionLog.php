<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_SavingTransactionLog;
use Illuminate\Http\Request;

class S_SavingTransactionLog
{
    protected $repository;

    function __construct(R_SavingTransactionLog $repository)
    {
        $this->repository = $repository;
    }
}

<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Saving\Model\M_SavingTransactionLog;

class R_SavingTransactionLog
{
    protected $model;

    function __construct(M_SavingTransactionLog $model)
    {
        $this->model = $model;
    }
}

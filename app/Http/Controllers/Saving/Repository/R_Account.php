<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Model\M_Account;
use Illuminate\Http\Request;

class R_Account
{
    protected $model;

    function __construct(M_Account $model)
    {
        $this->model = $model;
    }
}

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

    public function findById($id)
    {
        return $this->model::find($id);
    }

    public function findByAccNumber($accNumber)
    {
        return $this->model::where('acc_number', $accNumber)->first();
    }

    public function createOrUpdate($data, array $condition)
    {
        return $this->model::updateOrCreate(
            $condition,
            $data
        );
    }
}

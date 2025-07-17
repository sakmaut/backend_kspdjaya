<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Saving\Model\M_Account;

class R_Account
{
    protected $model;

    function __construct(M_Account $model)
    {
        $this->model = $model;
    }

    public function getAllAccount()
    {
        return $this->model::with(['customer', 'product_saving'])->get();
    }

    public function findById($id)
    {
        return $this->model::with(['customer', 'product_saving'])->where('id', $id)->first();
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

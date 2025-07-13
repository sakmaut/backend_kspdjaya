<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Model\M_Customers;
use Illuminate\Http\Request;

class R_Customers
{
    protected $model;

    function __construct(M_Customers $model)
    {
        $this->model = $model;
    }

    public function getAllCustomer()
    {
        return $this->model::with(['documents'])->limit(10)->get();
    }

    public function findById($id)
    {
        return $this->model::find($id);
    }

    public function generateCustCode($request)
    {
        return generateCustCode($request, $this->model->getTable(), 'CUST_CODE');
    }

    public function createOrUpdate($data, array $condition)
    {
        return $this->model::updateOrCreate(
            $condition,
            $data
        );
    }
}

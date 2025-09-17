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

    protected function getAllCustomer()
    {
        return $this->model::with(['documents'])->get();
    }

    protected function findById($id)
    {
        return $this->model::find($id);
    }

    protected function findBCustCode($custCode)
    {
        return $this->model::where('CUST_CODE', $custCode)->first();
    }

    protected function generateCustCode($request)
    {
        return generateCustCode($request, $this->model->getTable(), 'CUST_CODE');
    }

    protected function createOrUpdate($data, array $condition)
    {
        return $this->model::updateOrCreate(
            $condition,
            $data
        );
    }
}

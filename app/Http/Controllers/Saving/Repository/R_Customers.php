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
        return $this->model::limit(10)->get();
    }

    public function findById($id)
    {
        return $this->model::find($id);
    }
}

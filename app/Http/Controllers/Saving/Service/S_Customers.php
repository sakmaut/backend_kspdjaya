<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Repository\R_Customers;
use Exception;
use Illuminate\Http\Request;

class S_Customers
{
    protected $repository;

    function __construct(R_Customers $repository)
    {
        $this->repository = $repository;
    }

    public function getAllCustomer()
    {
        return $this->repository->getAllCustomer();
    }

    public function findById($id)
    {
        $data = $this->repository->findById($id);

        if (!$data) {
            throw new Exception("Data Not Found", 404);
        }

        return $data;
    }
}

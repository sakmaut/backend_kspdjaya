<?php

namespace App\Http\Controllers\Payment\Repository;

use App\Http\Controllers\Payment\Model\M_ArrearsBefore;

class R_ArrearsBefore
{
    protected $model;

    public function __construct(M_ArrearsBefore $model)
    {
        $this->model = $model;
    }

    protected function findArrearsByInvoice($invoiceNum)
    {
        return $this->model::where('NO_INVOICE', $invoiceNum)->get();
    }

    protected function create($request)
    {
        return  $this->model::create($request);
    }
}

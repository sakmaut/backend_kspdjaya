<?php

namespace App\Http\Controllers\Payment\Repository;

use App\Http\Controllers\Payment\Model\M_CreditScheduleBefore;

class R_CreditScheduleBefore
{
    protected $model;

    public function __construct(M_CreditScheduleBefore $model)
    {
        $this->model = $model;
    }

    protected function findCreditScheduleByInvoice($invoiceNum)
    {
        return $this->model::where('NO_INVOICE', $invoiceNum)->orderBy('INSTALLMENT_COUNT', 'ASC')->get();
    }

    protected function create($request)
    {
        return  $this->model::create($request);
    }
}

<?php

namespace App\Http\Controllers\Payment\Repository;

use App\Http\Controllers\Payment\Model\M_CreditBefore;

class R_CreditBefore
{
    protected $model;

    public function __construct(M_CreditBefore $model)
    {
        $this->model = $model;
    }

    protected function findCreditByInvoice($loan_number, $invoiceNum)
    {
        return $this->model::where('NO_INVOICE', $invoiceNum)->where('LOAN_NUMBER', $loan_number)->first();
    }

    protected function create($request)
    {
        return  $this->model::create($request);
    }
}

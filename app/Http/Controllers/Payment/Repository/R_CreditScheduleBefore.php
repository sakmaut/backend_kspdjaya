<?php

namespace App\Http\Controllers\Payment\Repository;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\Model\M_CreditScheduleBefore;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\FuncCall;

class R_CreditScheduleBefore
{
    protected $model;

    public function __construct(M_CreditScheduleBefore $model)
    {
        $this->model = $model;
    }

    protected function findCreditScheduleByInvoice($invoiceNum)
    {
        $this->model::where('NO_INVOICE', $invoiceNum)->get();
    }

    protected function create($request)
    {
        $this->model::create($request);
    }
}

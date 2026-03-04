<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_ArrearsBefore;
use Illuminate\Http\Request;

class S_ArrearsBefore extends R_ArrearsBefore
{
    protected $repository;
    protected $request;

    public function __construct(R_ArrearsBefore $repository, Request $request)
    {
        $this->repository = $repository;
        $this->request = $request;
    }

    public function getDataArrears($invoiceNum)
    {
        return $this->repository->findArrearsByInvoice($invoiceNum);
    }

    public function created($data, $no_invoice)
    {
        $fields = [
            'NO_INVOICE' => $no_invoice,
            'STATUS_REC' => $data->STATUS_REC,
            'LOAN_NUMBER' => $data->LOAN_NUMBER,
            'START_DATE' => $data->START_DATE,
            'END_DATE' => $data->END_DATE,
            'PAST_DUE_PCPL' => $data->PAST_DUE_PCPL,
            'PAST_DUE_INTRST' => $data->PAST_DUE_INTRST,
            'PAST_DUE_PENALTY' => $data->PAST_DUE_PENALTY,
            'PAID_PCPL' => $data->PAID_PCPL,
            'PAID_INT' => $data->PAID_INT,
            'PAID_PENALTY' => $data->PAID_PENALTY,
            'WOFF_PCPL' => $data->WOFF_PCPL,
            'WOFF_INT' => $data->WOFF_INT,
            'WOFF_PENALTY' => $data->WOFF_PENALTY,
            'PENALTY_RATE' => $data->PENALTY_RATE,
            'TRNS_CODE' => $data->TRNS_CODE,
            'CREATED_AT' => $data->CREATED_AT,
            'UPDATED_AT' => $data->UPDATED_AT,
        ];

        $this->repository->create($fields);
    }
}

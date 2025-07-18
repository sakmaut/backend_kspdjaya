<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_CreditBefore;
use Illuminate\Http\Request;

class S_CreditBefore extends R_CreditBefore
{
    protected $repository;
    protected $request;

    public function __construct(R_CreditBefore $repository, Request $request)
    {
        $this->repository = $repository;
        $this->request = $request;
    }

    public function getDataCredit($invoiceNum)
    {
        return $this->repository->findCreditByInvoice($invoiceNum);
    }

    public function created($data, $no_invoice)
    {
        $fields = [
            'NO_INVOICE' => $no_invoice,
            'LOAN_NUMBER' => $data->LOAN_NUMBER,
            'PCPL_ORI' => $data->PCPL_ORI,
            'INTRST_ORI' => $data->INTRST_ORI,
            'PAID_PRINCIPAL' => $data->PAID_PRINCIPAL,
            'PAID_INTEREST' => $data->PAID_INTEREST,
            'PAID_PENALTY' => $data->PAID_PENALTY,
            'DISCOUNT_PRINCIPAL' => $data->DISCOUNT_PRINCIPAL,
            'DISCOUNT_INTEREST' => $data->DISCOUNT_INTEREST,
            'DISCOUNT_PENALTY' => $data->DISCOUNT_PENALTY,
            'CREATED_BY' => $this->request->user()->id,
        ];

        $this->repository->create($fields);
    }
}

<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_CreditScheduleBefore;
use Illuminate\Http\Request;

class S_CreditScheduleBefore extends R_CreditScheduleBefore
{
    protected $repository;
    protected $request;

    public function __construct(R_CreditScheduleBefore $repository, Request $request)
    {
        $this->repository = $repository;
        $this->request = $request;
    }

    public function getDataCreditSchedule($loan_number, $invoiceNum)
    {
        return $this->repository->findCreditScheduleByInvoice($loan_number, $invoiceNum);
    }

    public function created($data, $no_invoice)
    {
        $fields = [
            'NO_INVOICE' => $no_invoice ?? '',
            'LOAN_NUMBER' => $data->LOAN_NUMBER ?? '',
            'INSTALLMENT_COUNT' => $data->INSTALLMENT_COUNT ?? 0,
            'PAYMENT_DATE' => $data->PAYMENT_DATE ?? null,
            'PRINCIPAL' => $data->PRINCIPAL ?? 0,
            'INTEREST' => $data->INTEREST ?? 0,
            'INSTALLMENT' => $data->INSTALLMENT ?? 0,
            'PRINCIPAL_REMAINS' => $data->PRINCIPAL_REMAINS ?? 0,
            'PAYMENT_VALUE_PRINCIPAL' => $data->PAYMENT_VALUE_PRINCIPAL ?? 0,
            'PAYMENT_VALUE_INTEREST' => $data->PAYMENT_VALUE_INTEREST ?? 0,
            'DISCOUNT_PRINCIPAL' => $data->DISCOUNT_PRINCIPAL ?? 0,
            'DISCOUNT_INTEREST' => $data->DISCOUNT_INTEREST ?? 0,
            'INSUFFICIENT_PAYMENT' => $data->INSUFFICIENT_PAYMENT ?? 0,
            'PAYMENT_VALUE' => $data->PAYMENT_VALUE ?? 0,
            'PAID_FLAG' => $data->PAID_FLAG ?? '',
            'CREATED_BY' => $this->request->user()->id,
        ];

        $this->repository->create($fields);
    }
}

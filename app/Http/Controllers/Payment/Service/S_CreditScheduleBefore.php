<?php

namespace App\Http\Controllers\Payment\Service;

use App\Http\Controllers\Payment\Repository\R_CreditScheduleBefore;
use Illuminate\Http\Request;

class S_CreditScheduleBefore extends R_CreditScheduleBefore
{
    protected $respository;
    protected $request;

    public function __construct(R_CreditScheduleBefore $respository, Request $request)
    {
        $this->respository = $respository;
        $this->request = $request;
    }

    public function getDataCreditSchedule($invoiceNum)
    {
        return $this->respository->findCreditScheduleByInvoice($invoiceNum);
    }

    public function created($data, $no_invoice)
    {
        $fields = [
            'NO_INVOICE' => $no_invoice,
            'LOAN_NUMBER' => $data->LOAN_NUMBER,
            'INSTALLMENT_COUNT' => $data->INSTALLMENT_COUNT,
            'PAYMENT_DATE' => $data->PAYMENT_DATE,
            'PRINCIPAL' => $data->PRINCIPAL,
            'INTEREST' => $data->INTEREST,
            'INSTALLMENT' => $data->INSTALLMENT,
            'PRINCIPAL_REMAINS' => $data->PRINCIPAL_REMAINS,
            'PAYMENT_VALUE_PRINCIPAL' => $data->PAYMENT_VALUE_PRINCIPAL,
            'PAYMENT_VALUE_INTEREST' => $data->PAYMENT_VALUE_INTEREST,
            'DISCOUNT_PRINCIPAL' => $data->DISCOUNT_PRINCIPAL,
            'DISCOUNT_INTEREST' => $data->DISCOUNT_INTEREST,
            'INSUFFICIENT_PAYMENT' => $data->INSUFFICIENT_PAYMENT,
            'PAYMENT_VALUE' => $data->PAYMENT_VALUE,
            'PAID_FLAG' => $data->PAID_FLAG,
            'CREATED_BY' => $this->request->user()->id,
        ];

        $this->respository->create($fields);
    }
}

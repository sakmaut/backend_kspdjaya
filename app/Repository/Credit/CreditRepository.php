<?php

namespace App\Repository\Credit;

use App\Models\M_Credit;

class CreditRepository
{
    protected $model;

    function __construct(M_Credit $model)
    {
        $this->model = $model;
    }

    public function creditWithCustomer($loan_number)
    {
        return $this->model::with(['customer:NAME,CUST_CODE,ADDRESS,RT,RW,PROVINCE,CITY,KELURAHAN,KECAMATAN'])
            ->where('LOAN_NUMBER', $loan_number)
            ->select('LOAN_NUMBER', 'CUST_CODE')
            ->first();
    }
}

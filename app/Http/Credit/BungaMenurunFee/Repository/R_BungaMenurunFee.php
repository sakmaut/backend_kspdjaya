<?php

namespace App\Http\Credit\BungaMenurunFee\Repository;

use App\Http\Credit\BungaMenurunFee\Model\M_BungaMenurunFee;

class R_BungaMenurunFee
{
    protected $model;

    public function __construct(M_BungaMenurunFee $model)
    {
        $this->model = $model;
    }

    public function showAll(){
        return $this->model::get();
    }

    public function findByid($id)
    {
        return $this->model->where('ID', $id)->first();
    }

    public function findFeeByLoanAmount($loanAmount)
    {
        return $this->model->where('LOAN_AMOUNT', $loanAmount)->first();
    }

    public function createOrUpdate($data)
    {
        $isUpdate = !empty($data['ID']);

        return $this->model->updateOrCreate(
            [
                'ID' => $data['ID'] ?? null
            ],
            [
                'LOAN_AMOUNT' => $data['LOAN_AMOUNT'],
                'INTEREST_PERCENTAGE' => $data['INTEREST_PERCENTAGE'] ?? null,
                'INSTALLMENT' => $data['INSTALLMENT'] ?? null,
                'ADMIN_FEE' => $data['ADMIN_FEE'] ?? null,
                'INTEREST_FEE' => $data['INTEREST_FEE'] ?? null,
                'PROCCESS_FEE' => $data['PROCCESS_FEE'] ?? null,
                'STATUS' => $data['STATUS'] ?? null,
                'UPDATED_BY' => auth()->user()->id ?? null,
                'UPDATED_AT' => now(),
                'CREATED_BY' => !$isUpdate ? $data['CREATED_BY'] : null,
            ]
        );
    }
}

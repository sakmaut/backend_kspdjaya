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

    public function showAll()
    {
        return $this->model
            ->whereNull('DELETED_AT')
            ->get();
    }

    public function findByid($id)
    {
        return $this->model->where('ID', $id)->first();
    }

    public function findFeeByLoanAmount($loanAmount)
    {
        return $this->model->where('LOAN_AMOUNT', $loanAmount)->first();
    }

    public function create($data)
    {
        return $this->model->create([
            'LOAN_AMOUNT' => $data['LOAN_AMOUNT'],
            'INTEREST_PERCENTAGE' => $data['INTEREST_PERCENTAGE'] ?? null,
            'INSTALLMENT' => $data['INSTALLMENT'] ?? null,
            'ADMIN_FEE' => $data['ADMIN_FEE'] ?? null,
            'INTEREST_FEE' => $data['INTEREST_FEE'] ?? null,
            'PROCCESS_FEE' => $data['PROCCESS_FEE'] ?? null,
            'STATUS' => $data['STATUS'] ?? null,
            'CREATED_BY' => $data['CREATED_BY'] ?? null,
            'CREATED_AT' => now(),
        ]);
    }

    public function update($id, $data)
    {
        $model = $this->findByid($id);

        if (!$model) {
            return null;
        }

        $model->update([
            'LOAN_AMOUNT' => $data['LOAN_AMOUNT'],
            'INTEREST_PERCENTAGE' => $data['INTEREST_PERCENTAGE'] ?? null,
            'INSTALLMENT' => $data['INSTALLMENT'] ?? null,
            'ADMIN_FEE' => $data['ADMIN_FEE'] ?? null,
            'INTEREST_FEE' => $data['INTEREST_FEE'] ?? null,
            'PROCCESS_FEE' => $data['PROCCESS_FEE'] ?? null,
            'STATUS' => $data['STATUS'] ?? null,
            'UPDATED_BY' => $data['UPDATED_BY'] ?? null,
            'UPDATED_AT' => now(),
        ]);

        return $model;
    }

    public function delete($id, $data)
    {
        $model = $this->findByid($id);

        if (!$model) {
            return false;
        }

        return $model->update([
            'DELETED_BY' => $data['DELETED_BY'] ?? null,
            'DELETED_AT' => now(),
        ]);
    }
}

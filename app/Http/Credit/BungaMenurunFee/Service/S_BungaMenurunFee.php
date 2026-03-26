<?php

namespace App\Http\Credit\BungaMenurunFee\Service;

use App\Http\Credit\BungaMenurunFee\Repository\R_BungaMenurunFee;

class S_BungaMenurunFee
{
    protected $repository;

    public function __construct(R_BungaMenurunFee $repository)
    {
        $this->repository = $repository;
    }

    public function showAll()
    {
        return $this->repository->showAll();
    }

    public function findByid($id)
    {
        return $this->repository->findByid($id);
    }

    public function getFeeByLoanAmount($loanAmount)
    {
        return $this->repository->findFeeByLoanAmount($loanAmount);
    }

    public function createOrUpdate($request)
    {
        $plafond = $request->Plafond ?? 0;
        $bunga = $request->Bunga ?? 0;
        $angsuran = $plafond * ($bunga / 100);

        $data = [
            'ID' => $request->ID,
            'LOAN_AMOUNT' => $plafond,
            'INTEREST_PERCENTAGE' => $bunga,
            'INSTALLMENT' => $angsuran,
            'ADMIN_FEE' => $request->BiayaAdmin,
            'STATUS' => $request->Status ? 'Active' : 'Inactive',
            'INTEREST_FEE' => $request->BiayaBunga,
            'PROCCESS_FEE' => $request->BiayaProses,
            'CREATED_BY' => $request->user()->id,
        ];

        return $this->repository->createOrUpdate($data);
    }
}

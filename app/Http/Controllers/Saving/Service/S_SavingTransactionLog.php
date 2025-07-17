<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_SavingTransactionLog;
use Exception;
use Illuminate\Http\Request;

class S_SavingTransactionLog extends R_SavingTransactionLog
{
    protected $repository;
    protected $s_account;

    function __construct(
        R_SavingTransactionLog $repository,
        S_Account $s_account
    )
    {
        $this->repository = $repository;
        $this->s_account = $s_account;
    }

    public function findTransactionLogByAccNumber($accNumber){
        return $this->s_account->findCustomerByAccNumber($accNumber);
    }

    public function create($request)
    {
        $accNumber = $request->no_rekening;
        $type      = $request->tipe_transaksi;
        $amount    = floatval($request->jumlah);
        $userId    = $request->user()->id;

        $this->s_account->updateBalanceTransaction($request, $accNumber, $type);

        $data = [
            'acc_number'       => $accNumber,
            'transaction_type' => $type,
            'amount'           => $amount,
            'description'      => $request->keterangan,
            'created_by'       => $userId,
        ];

        return $this->repository->create($data);
    }
}

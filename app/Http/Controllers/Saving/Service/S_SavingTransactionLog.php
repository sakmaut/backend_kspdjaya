<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_SavingTransactionLog;
use App\Models\M_Saving;
use App\Models\M_SavingLog;
use Exception;
use Illuminate\Http\Request;

class S_SavingTransactionLog extends R_SavingTransactionLog
{
    protected $repository;
    protected $s_account;

    function __construct(
        R_SavingTransactionLog $repository,
        S_Account $s_account
    ) {
        $this->repository = $repository;
        $this->s_account = $s_account;
    }

    public function findTransactionLogByAccNumber($accNumber)
    {
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

    public function createSaving($request)
    {
        $accNumber = $request->nomor_rekening;
        $type      = $request->sandi_transaksi;
        $amount    = floatval($request->nominal);
        $userId    = $request->user()->id;

        $saving = M_Saving::where('CUST_CODE', $request->cust_code)
            ->where('ACC_NUM', $accNumber)
            ->first();

        if ($saving) {
            if ($type == 'setor') {
                $saving->BALANCE += $amount;
                $trx_type = 'CREDIT';
            } else {
                $saving->BALANCE -= $amount;
                $trx_type = 'DEBIT';
            }

            $saving->save();
        } else {
            if ($type == 'setor') {
                $saving = M_Saving::create([
                    'CUST_CODE' => $request->cust_code,
                    'ACC_NUM'   => $accNumber,
                    'BALANCE'   => $amount
                ]);
                $trx_type = 'CREDIT';
            } else {
                return response()->json(['error' => 'Akun tidak ditemukan untuk penarikan.'], 400);
            }
        }
        $lastRow = M_SavingLog::where('BOOK', 1)
            ->whereHas('savings', function ($query) use ($request, $accNumber) {
                $query->where('CUST_CODE', $request->cust_code)
                    ->where('ACC_NUM', $accNumber);
            })
            ->max('ROW');

        $newRow = ($lastRow ?? 1) + 1;

        $newPage = ceil($newRow / 12);

        M_SavingLog::create([
            'SAVING_ID'   => $saving->ID ?? null,
            'TRX_TYPE'    => $trx_type,
            'TRX_DATE'    => now(),
            'BALANCE'     => $amount,
            'DESCRIPTION' => $request->keterangan ?? $type,
            'CREATED_BY'  => $userId,
            'BOOK'        => 1,
            'ROW'         => $newRow,
            'PAGE'        => $newPage
        ]);
        return $saving;
    }
}

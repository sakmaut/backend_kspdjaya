<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_Account;
use Exception;

class S_Account extends R_Account
{
    protected $repository;
    protected $s_customer;
    protected $s_product_saving;

    function __construct(
        R_Account $repository,
        S_Customers $s_customer,
        S_ProductSaving $s_product_saving
    ) {
        $this->repository = $repository;
        $this->s_customer = $s_customer;
        $this->s_product_saving = $s_product_saving;
    }

    public function getAllAccount()
    {
        return $this->repository->getAllAccount();
    }

    public function getAllAccountByCustCode($custCode)
    {
        return $this->repository->getAllAccountByCustCode($custCode);
    }

    public function findCustomerByAccNumber($accNumber)
    {
        $data = $this->repository->findByAccNumber($accNumber);

        if (!$data) {
            throw new Exception("Account Not Found", 404);
        }

        return $data;
    }

    public function findById($id)
    {
        $data = $this->repository->findById($id);

        if (!$data) {
            throw new Exception("Account Not Found", 404);
        }

        return $data;
    }

    public function create($request, $id = false, $type = "create")
    {
        $getCustomerId = $request->customer['id'];
        $getProductSavingId = $request->tabungan['id'];

        $customer = $this->s_customer->findById($getCustomerId);
        $productSaving = $this->s_product_saving->findById($getProductSavingId);

        $user = $request->user()->id;
        $branch = $request->user()->branch_id;

        $data = [
            'customer_id' => $customer->ID,
            'product_saving_id' => $productSaving->id,
            'acc_number' => $request->no_rekening,
            'acc_name' => $customer->NAME,
            'cust_code' => $customer->CUST_CODE,
            'branch' => $branch,
            'min_bal' => $request->setoran_awal,
            'plafond_amount' => $request->nama_produk,
            'date_last_trans' => now()
        ];

        if (isset($id) && $type != 'create') {

            $existing = $this->findById($id);

            $data['version']     = $existing->version + 1;
            $data['updated_by']  = $user;
            $data['updated_at']  = now();
        } else {
            $data['version']     = 1;
            $data['created_by']  = $user;
            $data['created_at']  = now();
        }

        return $this->repository->createOrUpdate(['id' => $id], $data);
    }

    public function updateBalanceTransaction($request, $accNumber, $type)
    {
        $account = $this->findCustomerByAccNumber($accNumber);
        $jumlah  = floatval($request->jumlah);
        $userId  = $request->user()->id;

        if ($type === 'debit') {
            $newBalance = $account->min_bal + $jumlah;
        } elseif ($type === 'credit') {
            $newBalance = $account->min_bal - $jumlah;
        } else {
            throw new \InvalidArgumentException("Invalid transaction type: $type");
        }

        return $account->update([
            'min_bal'         => $newBalance,
            'date_last_trans' => now(),
            'version'         => $account->version + 1,
            'updated_by'      => $userId,
            'updated_at'      => now(),
        ]);
    }
}

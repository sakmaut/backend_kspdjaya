<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_Account;
use App\Http\Controllers\Saving\Repository\R_Customers;
use App\Http\Controllers\Saving\Repository\R_ProductSaving;
use Exception;

class S_Account
{
    protected $repository;
    protected $r_customer;
    protected $r_product_saving;

    function __construct(
        R_Account $repository,
        R_Customers $r_customer,
        R_ProductSaving $r_product_saving
    ) {
        $this->repository = $repository;
        $this->r_customer = $r_customer;
        $this->r_product_saving = $r_product_saving;
    }

    public function getAllAccount()
    {
        return $this->repository->getAllAccount();
    }

    private function findCustomerById($id)
    {
        $data = $this->r_customer->findById($id);

        if (!$data) {
            throw new Exception("Customer Not Found", 404);
        }

        return $data;
    }

    private function findProductSavingById($id)
    {
        $data = $this->r_product_saving->findById($id);

        if (!$data) {
            throw new Exception("Product Saving Not Found", 404);
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

    public function createOrUpdate($request, $id = false, $type = "create")
    {
        $getCustomerId = $request->customer['id'];
        $getProductSavingId = $request->tabungan['id'];

        $customer = $this->findCustomerById($getCustomerId);
        $productSaving = $this->findProductSavingById($getProductSavingId);

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
}

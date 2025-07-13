<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_Account;
use Exception;

class S_Account
{
    protected $repository;

    function __construct(R_Account $repository)
    {
        $this->repository = $repository;
    }

    private function findById($id)
    {
        $data = $this->repository->findById($id);

        if (!$data) {
            throw new Exception("Data Not Found", 404);
        }

        return $data;
    }

    private function findByAccNumber($accNumber)
    {
        $checkAccNumber =  $this->repository->findByAccNumber($accNumber);

        if (!$checkAccNumber) {
            throw new Exception("Failed Acc Number Is Exist", 500);
        }

        return $checkAccNumber;
    }

    public function createOrUpdate($request, $id = false, $type = "create")
    {
        $getAccNumber = $request->no_rekening;

        $existing = $id ? $this->findById($id) : $this->findByAccNumber($getAccNumber);
        $user = $request->user()->id;

        $data = [
            // 'product_code' => $productCode,
            'product_name' => $request->nama_produk,
            'product_type' => $request->jenis_produk,
            'interest_rate' => $request->suku_bunga,
            'min_deposit' => $request->setoran_minimum,
            'admin_fee' => $request->biaya_administrasi,
            'term_length' => $request->jangka_waktu
        ];

        if ($existing && $type != 'create') {

            $data['version']     = $existing->version + 1;
            $data['updated_by']  = $user;
            $data['updated_at']  = now();

            // $key = $id ? ['id' => $id] : ['acc_number' => $productCode];
        } else {
            $data['version']     = 1;
            $data['created_by']  = $user;
            $data['created_at']  = now();

            // $key = ['acc_number' => $productCode];
        }

        // return $this->repository->updateOrCreate($key, $data);
    }
}

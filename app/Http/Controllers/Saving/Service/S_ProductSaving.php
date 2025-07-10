<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_ProductSaving;
use Exception;

class S_ProductSaving
{
    protected $repository;

    function __construct(R_ProductSaving $repository)
    {
        $this->repository = $repository;
    }

    public function findById($id)
    {
        $data = $this->repository->findById($id);

        if (!$data) {
            throw new Exception("Data Not Found", 404);
        }

        return $data;
    }

    public function getAllDataProductSaving()
    {
        return $this->repository->getAllDataProductSaving();
    }

    public function createOrUpdate($request, $id = false, $type = "create")
    {
        $productCode = $this->repository->generateCodeProductSaving();

        $existing = $id ? $this->findById($id) : $this->repository->findByProductCode($productCode);
        $user = $request->user()->id;

        $data = [
            'product_code' => $productCode,
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

            $key = $id ? ['id' => $id] : ['product_code' => $productCode];
        } else {
            $data['version']     = 1;
            $data['created_by']  = $user;
            $data['created_at']  = now();

            $key = ['product_code' => $productCode];
        }

        return $this->repository->updateOrCreate($key, $data);
    }
}

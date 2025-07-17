<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Saving\Repository\R_ProductSaving;
use Exception;

class S_ProductSaving extends R_ProductSaving
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
            throw new Exception("Product Saving Not Found", 404);
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
            'product_name' => $request->nama_jenis ?? '',
            'product_type' => $request->kode_jenis ?? '',
            'interest_rate' => $request->bunga ?? 0,
            'min_deposit' => $request->minimal_saldo,
            'admin_fee' => $request->biaya_administrasi ?? 0,
            'term_length' => $request->jangka_waktu ?? 0,
            'description' => $request->deskripsi ?? '',
            'status' => $request->status ?? ''
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

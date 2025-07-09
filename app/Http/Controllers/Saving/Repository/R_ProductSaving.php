<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Saving\Model\M_ProductSaving;

class R_ProductSaving
{
    protected $model;

    function __construct(M_ProductSaving $model)
    {
        $this->model = $model;
    }

    public function findById($id)
    {
        return $this->model::find($id);
    }

    public function generateCode()
    {
        return generateAutoCode($this->model, 'product_code', 'SFI/PRD/');
    }

    public function findByProductCode($code)
    {
        return $this->model::where('product_code', $code)->first();
    }

    public function getAllDataProductSaving()
    {
        return $this->model::all();
    }

    public function updateOrCreate(array $where, array $data)
    {
        return $this->model::updateOrCreate($where, $data);
    }
}

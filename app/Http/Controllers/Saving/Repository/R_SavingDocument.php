<?php

namespace App\Http\Controllers\Saving\Repository;

use App\Http\Controllers\Saving\Model\M_SavingDocument;
use Illuminate\Http\Request;

class R_SavingDocument
{
    protected $model;

    function __construct(M_SavingDocument $model)
    {
        $this->model = $model;
    }

    public function findById($id)
    {
        return $this->model::find($id);
    } 

    public function createOrDelete($data,$id)
    {
        $existing = $this->findById($id);

        if ($existing) {
            $existing->delete(); 
        } else {
            $this->model->create($data);
        }
    }
}

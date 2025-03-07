<?php

namespace App\Http\Controllers\Repositories\CollateralTransaction;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Collateral\CollateralRepository;
use App\Models\M_CrCollateral;
use Illuminate\Http\Request;

class CollateralTransactionRepository implements CollateralTransactionInterface
{
    protected $collateralEntity;
    protected $collateralRepository;

    function __construct(M_CrCollateral $collateralEntity, CollateralRepository $collateralRepository)
    {
        $this->collateralEntity = $collateralEntity;
        $this->collateralRepository = $collateralRepository;
    }
}

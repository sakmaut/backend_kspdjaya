<?php

namespace App\Http\Controllers\Repositories\Collateral;

use App\Http\Controllers\Controller;
use App\Models\M_CrCollateral;
use Illuminate\Http\Request;

class CollateralRepository implements CollateralInterface
{
    protected $collateralEntity;

    function __construct(M_CrCollateral $collateralEntity)
    {
        $this->collateralEntity = $collateralEntity;
    }

    function findCollateralById($id)
    {
        return $this->collateralEntity::where('ID', $id)->first();
    }

    function getListAllCollateral()
    {
        $query = $this->collateralEntity::with(['credit', 'originBranch', 'currentBranch'])->get();

        return $query;
    }

    function searchCollateralList($request)
    {
        $query = $this->collateralEntity::with(['credit', 'originBranch', 'currentBranch'])->get();

        return $query;
    }
}

<?php

namespace App\Http\Controllers\Repositories\CollateralTransaction;

use App\Models\M_CrCollateral;
use Illuminate\Http\Request;

class CollateralTransactionRepository implements CollateralTransactionInterface
{
    protected $collateralEntity;

    function __construct(M_CrCollateral $collateralEntity)
    {
        $this->collateralEntity = $collateralEntity;
    }

    function showAllCollateralListInOriginalBranch($request)
    {
        $branch = $request->user()->branch_id;

        $query = $this->collateralEntity::with(['credit.customer', 'originBranch', 'currentBranch'])
            ->where('COLLATERAL_FLAG', $branch)
            ->where('LOCATION_BRANCH', $branch)
            ->lazy(1000);

        return $query;
    }
}

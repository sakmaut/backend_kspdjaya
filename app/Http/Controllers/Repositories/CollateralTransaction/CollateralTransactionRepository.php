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
        $search = $request->query('search');
        $branch = $request->user()->branch_id;

        $query = $this->collateralEntity::with(['credit.customer', 'originBranch', 'currentBranch'])
            ->where('LOCATION_BRANCH', $branch)
            ->lazy(100);

        return $query;
    }
}

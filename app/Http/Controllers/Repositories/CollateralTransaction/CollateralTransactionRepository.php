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
        $search = $request->query('type');
        $branch = $request->user()->branch_id;
        
        $query = [];
        if(!empty($search)){
            $query = $this->collateralEntity::with(['credit.customer', 'originBranch', 'currentBranch']);

            switch ($search) {
                case 'origin':
                    $query->where('COLLATERAL_FLAG', $branch);
                    break;
                case 'current':
                    $query->where('LOCATION_BRANCH', $branch);
                    break;
                case 'proc':
                    $query->where('LOCATION_BRANCH', $branch);
                    break;
            }

            $query->paginate(10);
        }
       
        return $query;
    }
}

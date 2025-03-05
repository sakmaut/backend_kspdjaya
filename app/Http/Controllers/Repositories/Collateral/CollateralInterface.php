<?php

namespace App\Http\Controllers\Repositories\Collateral;


interface CollateralInterface
{
    function findCollateralById($id);
    function getListAllCollateral();
    function searchCollateralList($request);
}

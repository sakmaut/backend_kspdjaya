<?php

namespace App\Http\Controllers\Repositories\Branch;

use GuzzleHttp\Psr7\Request;

interface BranchRepositoryInterface
{
    function getActiveBranch();
    function findBranchById($id);
    function findBranchByCode($code);
    function findBranchByName($name);
    function getMaxCodeBranch();
    function create($request);
}

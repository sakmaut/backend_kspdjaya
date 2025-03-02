<?php

namespace App\Http\Controllers\Repositories\Branch;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use Illuminate\Http\Request;

class BranchRepository implements BranchRepositoryInterface
{
    protected $branchEntity;

    function __construct(M_Branch $branchEntity)
    {
        $this->branchEntity = $branchEntity;
    }

    function getActiveBranch()
    {
        return $this->branchEntity::where(function ($query) {
                    $query->whereNull('DELETED_BY')
                        ->orWhere('DELETED_BY', '');
                })
                ->get();
    }
}

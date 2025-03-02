<?php

namespace App\Http\Controllers\Repositories\Branch;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

interface BranchRepositoryInterface
{
    function getActiveBranch();
}

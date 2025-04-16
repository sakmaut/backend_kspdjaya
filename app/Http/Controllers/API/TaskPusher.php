<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_TaskPusher;
use App\Models\M_Tasks;
use Illuminate\Http\Request;

class TaskPusher extends Controller
{
    public function index()
    {
        return response()->json(M_Tasks::all());
    }
}

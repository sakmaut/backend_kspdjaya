<?php

namespace App\Http\Controllers;

use App\Models\M_HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    
    public function setEmployeeData($request)
    {
        $data_employee = M_HrEmployee::where('ID', $request->user()->employee_id)->first();

        return $data_employee;
    }

}

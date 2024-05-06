<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_HrEmployee;
use Illuminate\Support\Facades\Auth;


class TestController extends Controller
{
    public function index (){

        $userId =Auth::user()->employee_id;
        $test = self::subOrdinateList($userId);
        
        return response()->json(['message' => 'Ok', "status" => 200, 'response' =>  $test], 200);

    }

    public static function subOrdinateList($userId)
    {
        $employeeData = M_HrEmployee::subOrdinateList($userId);

        foreach ($employeeData as $employee) {
            $subordinates = self::subOrdinateList($employee->employee_id);

            if (!empty($subordinates)) {
                $employeeData->push(...$subordinates->toArray());
            }
        }

        return $employeeData->isEmpty() ? null : $employeeData;
    }


    // public static function subOrdinateListHierarky($userId)
    // {
    //     $employeeData = M_HrEmployee::subOrdinateList($userId);

    //     foreach ($employeeData as $key => $employee) {
    //         $subordinates = self::subOrdinateListHierarky($employee->employee_id);
            
    //         if (!empty($subordinates)) {
    //             $employeeData[$key]->sub_list = $subordinates->toArray();
    //         }
    //     }

    //     return $employeeData->isEmpty() ? null : $employeeData;
    // }


    // function buildEmployeeHierarchy($employees, $parentId = null, $searchId = null) {
    //     $employeeTree = [];
    //     foreach ($employees as $employee) {
    //         if ($employee->spv_id == $parentId) {
    //             $subEmployees = $this->buildEmployeeHierarchy($employees, $employee->id, $searchId);
    //             $employeeData = [
    //                 "id" => $employee->id,
    //                 "user_id" => $employee->user_id,
    //                 "username" => $employee->username,
    //                 "email" => $employee->email,
    //                 "nik" => $employee->NIK,
    //                 "nama" => $employee->NAMA,
    //                 "bagian" => $employee->bagian,
    //                 "jabatan" => $employee->jabatan,
    //                 "spv_id" => $employee->spv_id,
    //                 "spv_name" => $employee->spv_name,
    //                 "USE_FLAG" => $employee->USE_FLAG,
    //                 "sub_list" => $subEmployees
    //             ];

    //             $employeeTree[] = $employeeData;
    //         }
    //     }
    //     return $employeeTree;
    // }
    
}

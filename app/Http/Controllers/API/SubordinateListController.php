<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class SubordinateListController extends Controller
{
    public function spvSearch(Request $request){
        try {
            $request->validate([
                'employee_id' => 'required|string',
            ]);

            $data = M_HrEmployee::getSpv($request->employee_id);

            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (QueryException $e) {
            // ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function getHierarchy (Request $request){
        try {
            $request->validate([
                'employee_id' => 'required|string',
            ]);

            $dataList = self::subOrdinateList($request->employee_id);
            
            return response()->json(['message' => 'Ok', "status" => 200, 'response' =>  $dataList], 200);
        } catch (QueryException $e) {
            // ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            // ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function subOrdinateList($userId)
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

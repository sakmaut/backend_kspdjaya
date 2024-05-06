<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CreditType;
use App\Models\M_CreditType;

class CreditTypeController extends Controller
{
    public function index($status = null)
    {
        if ($status === null) {
            $fetchData = M_CreditType::all();
        }else{

            if (!in_array($status, ['active', 'inactive'])) {
                return response()->json(['message' => 'Error', "status" => 404, 'response' => 'Status Not Found'], 404);
            }    

            $fetchData = M_CreditType::where('status', $status)->get();
        }
       
        $dto =  R_CreditType::collection($fetchData);
        
        return response()->json(['message' => 'Ok', "status" => 200, 'response' => $dto], 200);
    }
}

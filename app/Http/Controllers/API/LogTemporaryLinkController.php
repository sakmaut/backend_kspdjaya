<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_TemporaryLink;
use App\Models\M_LogTemporaryLink;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogTemporaryLinkController extends Controller
{
    public function index($id){
        try {
            
            $data =  M_LogTemporaryLink::where('id', $id)->get();
            $dto = R_TemporaryLink::collection($data);

            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

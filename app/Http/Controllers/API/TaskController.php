<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Task;
use App\Models\M_Task;
use App\Models\M_TaskPusher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data =  M_Task::joinTaskPusher($request->user()->id);

            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    // public function show(Request $req,$id)
    // {
    //     try {
    //         $check = M_Task::where('id',$id)->firstOrFail();

    //         ActivityLogger::logActivity($req,"Success",200);
    //         return response()->json(['message' => 'OK',"status" => 200,'response' => $check], 200);
    //     } catch (ModelNotFoundException $e) {
    //         ActivityLogger::logActivity($req,'Data Not Found',404);
    //         return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
    //     } catch (\Exception $e) {
    //         ActivityLogger::logActivity($req,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
    //     }
    // }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $uuid = Uuid::uuid4()->toString();

            $task = [
                'ID' => $uuid,
                'NAME' => $request->name,
                'FLAG' => $request->flag,
                'ROUTE' => $request->route,
                'PAR1' => $request->par1,
                'STATUS' => $request->status
            ];
     
            M_Task::create($task);

            $task_pusher = [
                'TASK_ID' => $uuid,
                'USER_ID'  => $request->user()->id,
                "CREATED"  => Carbon::now()->format('Y-m-d'),
                'STATUS'  => 'active',
                'TYPE'  => $request->type
            ];

            M_TaskPusher::create($task_pusher);
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Pusher created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    // public function update(Request $request,$id)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $request->validate([
    //             'code' => 'unique:branch,code,'.$id,
    //             'name' => 'unique:branch,name,'.$id,
    //         ]);

    //         $users = M_Branch::findOrFail($id);

    //         $request['MOD_USER'] = $request->user()->id;
    //         $request['MOD_DATE'] = Carbon::now()->format('Y-m-d');

    //         $data = array_change_key_case($request->all(), CASE_UPPER);

    //         $users->update($data);

    //         DB::commit();
    //         ActivityLogger::logActivity($request,"Success",200);
    //         return response()->json(['message' => 'Cabang updated successfully', "status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,'Data Not Found',404);
    //         return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }
}

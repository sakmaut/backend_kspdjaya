<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\M_TaskPusher;
use App\Models\M_Tasks;
use Illuminate\Http\Request;

class TaskPusher extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getCurrentBranch = $request->user()->branch_id;
            $getCurrentPosition = $request->user()->position;

            $setPositionAvailable = ['ADMIN','KAPOS'];

            if(in_array($setPositionAvailable,$getCurrentPosition)){
                $data = M_Tasks::where([
                    'created_branch' => $getCurrentBranch,
                    'recipient_id' =>  $getCurrentPosition,
                    'status' => 'PENDING'
                ])->whereIn('type', ['request_payment'])->get();
            }elseif($getCurrentPosition == 'HO') {
                $data = M_Tasks::where([
                    'recipient_id' =>  $getCurrentPosition,
                    'status' => 'PENDING'
                ])->whereIn('type', ['payment', 'request_discount'])->get();
            }
            

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}

<?php

namespace App\Http\Controllers\Repositories\TasksLogging;

use App\Models\M_Tasks;
use App\Models\M_TasksLogging;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class TasksRepository
{
    protected $tasksModel;
    protected $tasksLogModel;

    function __construct(M_Tasks $tasksModel, M_TasksLogging $tasksLogModel)
    {
        $this->tasksModel = $tasksModel;
        $this->tasksLogModel = $tasksLogModel;
    }

    function create($request, $type, $type_id, $code, $status, $descr)
    {
        $data = [
            'type' => $type,
            'status' => $status,
            'descr' => $descr,
            'recipient_id'  => $code,
            'created_id' => $request->user()->id,
            'created_by' => $request->user()->fullname,
            'created_branch' => $request->user()->branch_id,
            'created_position' => $request->user()->position
        ];

        $check =  $this->tasksModel->where('type_id', $type_id)->first();

        if ($check) {
            $check->update($data);
        } else {
            $data['type_id'] = $type_id;
            $this->tasksModel::create($data);
        }
    }
}

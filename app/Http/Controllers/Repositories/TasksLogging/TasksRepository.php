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
        $user = $request->user();
        $timestamp = now();

        $data = [
            'type' => $type,
            'status' => $status,
            'descr' => $descr,
            'recipient_id' => $code,
        ];

        $check = $this->tasksModel->where('type_id', $type_id)->first();

        if ($check) {
            $data['updated_by'] = $user->id;
            $data['updated_at'] = $timestamp;
            $check->update($data);
            $taskId = $check->id;
        } else {
            $data = array_merge($data, [
                'type_id' => $type_id,
                'created_id' => $user->id,
                'created_by' => $user->fullname,
                'created_branch' => $user->branch_id,
                'created_position' => $user->position,
                'created_at' => $timestamp,
            ]);
            $execute = $this->tasksModel::create($data);
            $taskId = $execute->id;
        }

        // Log the task
        $logData = array_merge($data, [
            'tasks_id' => $taskId,
            'type_id' => $type_id,
            'created_id' => $user->id,
            'created_by' => $user->fullname,
            'created_branch' => $user->branch_id,
            'created_position' => $user->position,
            'created_at' => $timestamp,
        ]);

        $this->tasksLogModel::create($logData);
    }
}

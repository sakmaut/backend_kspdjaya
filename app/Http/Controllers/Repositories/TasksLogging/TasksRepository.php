<?php

namespace App\Http\Controllers\Repositories\TasksLogging;

use App\Models\M_Tasks;
use App\Models\M_TasksLogging;

class TasksRepository
{
    protected $tasksModel;
    protected $tasksLogModel;

    function __construct(M_Tasks $tasksModel, M_TasksLogging $tasksLogModel)
    {
        $this->tasksModel = $tasksModel;
        $this->tasksLogModel = $tasksLogModel;
    }

    function create($request, $title, $type, $type_id, $status, $descr, $to = '')
    {
        $user = $request->user();
        $timestamp = now();

        $getCurrentPosition = $user->position;
        $setPositionAvailable  = ['mcf', 'kolektor'];
        $checkposition = in_array(strtolower($getCurrentPosition), $setPositionAvailable);

        if ($checkposition && $type == 'request_payment') {
            $setTo = 'ADMIN';
        } else {
            $setTo = 'HO';
        }

        $data = [
            'type' => $type,
            'status' => $status,
            'descr' => $descr ?? '',
            'recipient_id' => $to != '' ? $to : $setTo,
        ];

        $check = $this->tasksModel->where('type_id', $type_id)->first();

        if ($check) {
            $data['updated_by'] = $user->id;
            $data['updated_at'] = $timestamp;
            $check->update($data);
            $taskId = $check->id;
        } else {
            $data = array_merge($data, [
                'title' => $title,
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

        // $logData = array_merge($data, [
        //     'tasks_id' => $taskId,
        //     'type_id' => $type_id,
        //     'created_id' => $user->id,
        //     'created_by' => $user->fullname,
        //     'created_branch' => $user->branch_id,
        //     'created_position' => $user->position,
        //     'created_at' => $timestamp,
        // ]);

        // $this->tasksLogModel::create($logData);
    }
}

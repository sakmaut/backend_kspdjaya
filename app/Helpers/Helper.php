<?php

use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_TransactionLog;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

if (!function_exists('checkDateIfNull')) {
    function checkDateIfNull($param)
    {
        return $param == null ? null:date('Y-m-d',strtotime($param));
    }
}


if (!function_exists('compareData')) {
    function compareData($modelName, $id, $request)
    {
        $dataOLD = $modelName::where('id', $id)->first();

        $dataold = [];
        foreach ($dataOLD->getAttributes() as $key => $value) {
            $dataold[$key] = $value;
        }
    
        $datanew = [];
        foreach ($dataOLD->getFillable() as $field) {
            $requestField = str_replace('.', '_', $field);
            if ($request->has($requestField)) {
                $datanew[$field] = $request->$requestField;
            }
        }

        $changes = [];
        foreach ($datanew as $key => $newValue) {
            if (isset($dataold[$key]) && $dataold[$key] != $newValue) {
                $changes[$key] = $newValue;
            }
        }
    
        foreach ($changes as $key => $value) {
            $dataLog = [
                'id' => Uuid::uuid7()->toString(),
                'table_name' => $dataOLD->getTable(),
                'table_id' => $id,
                'field_name' => $key,
                'old_value' => $dataOLD->$key,
                'new_value' => $value,
                'altered_by' => $request->user()->id?? 0,
                'altered_time' => Carbon::now()->format('Y-m-d H:i:s')
            ];
    
            M_TransactionLog::create($dataLog);
        }
    }
}
// private function nikCounter()
//     {
//         $checkMax = M_HrEmployee::max('NIK');

//         $currentDate = Carbon::now();
//         $year = substr($currentDate->format('Y'), -2);
//         $month = $currentDate->format('m');
//         $lastSequence = (int) substr($checkMax, 4, 3);
//         $lastSequence++;

//         $generateCode = $year . $month . sprintf("%03s", $lastSequence);

//         return $generateCode;
//     }
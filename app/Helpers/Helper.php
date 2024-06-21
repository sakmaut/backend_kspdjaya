<?php

use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

if (!function_exists('checkDateIfNull')) {
    function checkDateIfNull($param)
    {
        return $param == null ? null:date('Y-m-d',strtotime($param));
    }
}


if (!function_exists('compareData')) {
    function compareData($modelName, $id, $newData,$request)
    {
        $dataOLD = $modelName::find($id);

        if (!$dataOLD) {
            return [];
        }

        $differences = [];

        $excludeKeys = ['updated_by', 'updated_at','mod_user','mod_date'];

        foreach ($newData as $key => $value) {

            if (in_array(strtolower($key), $excludeKeys)) {
                continue;
            }

            if ($dataOLD->$key != $value) {
                $differences[$key] = $value;
            }
        }

        if (!empty($differences)) {
            foreach ($differences as $key => $newValue) {
                $dataLog = [
                    'id' => Uuid::uuid7()->toString(),
                    'table_name' => $dataOLD->getTable(),
                    'table_id' => $id,
                    'field_name' => $key,
                    'old_value' => $dataOLD[$key],
                    'new_value' => $newValue,
                    'altered_by' => $request->user()->id ?? 0,
                    'altered_time' => Carbon::now()->format('Y-m-d H:i:s')
                ];
    
                M_TransactionLog::create($dataLog);
            }
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
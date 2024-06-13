<?php

if (!function_exists('checkDateIfNull')) {
    function checkDateIfNull($param)
    {
        return $param == null ? null:date('Y-m-d',strtotime($param));
    }
}

if (!function_exists('createAutoCode')) {
    function createAutoCode($table, $field, $prefix)
    {
        $query = $table::max($field);
		$_trans = date("Ymd");

        $noUrut = (int) substr(!empty($query) ? $query : 0, 17, 5);

		$noUrut++;
		$generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05s", $noUrut);

		return $generateCode;
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
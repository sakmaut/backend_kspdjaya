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

        $noUrut = (int) substr(!empty($query->field) ? $query->field : 0, 17, 5);

		$noUrut++;
		$generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05s", $noUrut);

		return $generateCode;
    }
}

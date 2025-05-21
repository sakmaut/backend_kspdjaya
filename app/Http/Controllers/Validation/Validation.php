<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use App\Models\M_Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Validation
{
    protected $creditModel;

    function __construct(M_Credit $creditModel)
    {
        $this->creditModel = $creditModel;
    }

    function checkValidation($params = [])
    {
        $orderNumber = $params['order_number'] ?? "";
        $ktp = $params['ktp'] ?? '';
        $kk = $params['kk'] ?? '';
        $message = [];

        $activeCredits = function ($orderNumber, $field, $value) {
            return DB::table('credit as a')
                ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->where('a.STATUS', 'A')
                ->where("b.$field", $value)
                ->where('a.ORDER_NUMBER', '!=', $orderNumber)
                ->count();
        };

        if ($activeCredits($orderNumber, 'ID_NUMBER', $ktp) > 1) {
            $message[] = "KTP : No KTP {$ktp} Masih Ada yang Aktif";
        }

        if ($activeCredits($orderNumber, 'KK_NUMBER', $kk) > 2) {
            $message[] = "KK : No KK {$kk} Aktif Lebih Dari 2";
        }

        return $message;
    }
}

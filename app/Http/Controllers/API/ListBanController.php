<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListBanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = $this->queryListBan();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function queryListBan(){
        $result = DB::select(
                "select case when c.ID is not null and a.ACC_KEYS like '%POKOK%' then 'TUNGGAKAN_POKOK'
                            when c.ID is not null and a.ACC_KEYS like '%BUNGA%' then 'TUNGGAKAN_BUNGA'
                            when c.ID is null and a.ACC_KEYS like '%POKOK%' then 'BAYAR_POKOK'
                            when c.ID is null and a.ACC_KEYS like '%BUNGA%' then 'TUNGGAKAN_BUNGA'
                            else 'BAYAR_LAINNYA' end as JENIS, 
                        b.BRANCH, b.ENTRY_DATE, a.ORIGINAL_AMOUNT
                from payment_detail a
                    inner join payment b on b.ID = a.PAYMENT_ID
                    left join arrears c on c.ID = b.ARREARS_ID"
        );

        return $result;
    }
}

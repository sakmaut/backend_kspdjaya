<?php

namespace App\Http\Controllers;

use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrPersonal;
use App\Models\M_CrProspect;
use App\Models\M_DeuteronomyTransactionLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

class Welcome extends Controller
{
    public function index(Request $request)
    {
        $groupedData = [];
        $groupedData = [];
        foreach ($request->all() as $item) {
            $key = $item['LOAN_NUMBER']; // Group by LOAN_NUMBER only

            // If the group doesn't exist yet, initialize it
            if (!isset($groupedData[$key])) {
                // Preserve other fields (except no_invoice, which will be kept as it is for each record)
                $groupedData[$key] = [
                    "PAYMENT_TYPE" => $item['PAYMENT_TYPE'],
                    "STTS_PAYMENT" => $item['STTS_PAYMENT'],
                    "METODE_PEMBAYARAN" => $item['METODE_PEMBAYARAN'],
                    "BRANCH_CODE" => $item['BRANCH_CODE'],
                    "LOAN_NUMBER" => $item['LOAN_NUMBER'],
                    "no_invoice" => $item['no_invoice'],  // keep original no_invoice
                    "tgl_angsuran" => $item['tgl_angsuran'],  // assuming we want to keep the latest tgl_angsuran for each LOAN_NUMBER
                    "angsuran_ke" => $item['angsuran_ke'],
                    "installment" => $item['installment'],
                    "diskon_denda" => $item['diskon_denda'],
                    "flag" => $item['flag'],
                    "details" => []
                ];
            }

            // Add bayar_angsuran and bayar_denda to the details array
            $groupedData[$key]['details'][] = [
                'bayar_angsuran' => $item['bayar_angsuran'],
                'bayar_denda' => $item['bayar_denda']
            ];
        }

        // Output the grouped data
        return response()->json($groupedData, 200);
    }
   
}

<?php

use App\Models\M_Branch;
use App\Models\M_CustomerDocument;
use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

if (!function_exists('generateCode')) {
    function generateCode($request, $table, $column, $prefix = '') {
        // Get branch ID from the user request
        $branchId = $request->user()->branch_id;
        // Find branch based on the branch ID
        $branch = M_Branch::find($branchId);
    
        if (!$branch) {
            throw new Exception("Branch not found.");
        }
    
        // Get the branch code number
        $branchCodeNumber = $branch->CODE_NUMBER;
    
        // Retrieve the latest record from the table based on the column
        $latestRecord = DB::table($table)->latest($column)->first();
        // Extract the last sequence number from the column value (if exists)
        $lastSequence = $latestRecord ? (int) substr($latestRecord->$column, -5) + 1 : 1;
    
        // Get current year and month
        $currentDate = Carbon::now();
        $year = $currentDate->format('y');
        $month = $currentDate->format('m');
    
        // Generate the code based on the presence of the prefix
        $generateCode = $prefix !== '' && $prefix !== null
            ? sprintf("%s%s%s%s%05d", $prefix, $branchCodeNumber, $year, $month, $lastSequence)
            : sprintf("%s%s%s%05d", $branchCodeNumber, $year, $month, $lastSequence);
    
        return $generateCode;
    }
    
}

if (!function_exists('generateCustCode')) {
    function generateCustCode($request, $table, $column) {
        $branchId = $request->user()->branch_id;
        $branch = M_Branch::find($branchId);
    
        if (!$branch) {
            throw new Exception("Branch not found.");
        }
    
        $branchCodeNumber = $branch->CODE_NUMBER;
    
        $latestRecord = DB::table($table)->latest($column)->first();
        $lastSequence = $latestRecord ? (int) substr($latestRecord->$column, 3, 5) + 1 : 1;
    

        $generateCode = sprintf("%s%05d", $branchCodeNumber, $lastSequence);
    
        return $generateCode;
    }
}


if (!function_exists('angkaKeKata')) {
    function angkaKeKata($angka,$rupiah = true) {
        $angka = abs($angka);
        $kata = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        $hasil = "";
    
        if ($angka < 12) {
            $hasil = " " . $kata[$angka];
        } else if ($angka < 20) {
            $hasil = angkaKeKata($angka - 10) . " belas";
        } else if ($angka < 100) {
            $hasil = angkaKeKata(floor($angka / 10)) . " puluh" . angkaKeKata($angka % 10);
        } else if ($angka < 200) {
            $hasil = " seratus" . angkaKeKata($angka - 100);
        } else if ($angka < 1000) {
            $hasil = angkaKeKata(floor($angka / 100)) . " ratus" . angkaKeKata($angka % 100);
        } else if ($angka < 2000) {
            $hasil = " seribu" . angkaKeKata($angka - 1000);
        } else if ($angka < 1000000) {
            $hasil = angkaKeKata(floor($angka / 1000)) . " ribu" . angkaKeKata($angka % 1000);
        } else if ($angka < 1000000000) {
            $hasil = angkaKeKata(floor($angka / 1000000)) . " juta" . angkaKeKata($angka % 1000000);
        } else if ($angka < 1000000000000) {
            $hasil = angkaKeKata(floor($angka / 1000000000)) . " milyar" . angkaKeKata($angka % 1000000000);
        } else if ($angka < 1000000000000000) {
            $hasil = angkaKeKata(floor($angka / 1000000000000)) . " triliun" . angkaKeKata($angka % 1000000000000);
        }

        if($rupiah){
            $final_result = str_replace("rupiah", "", $hasil) . " rupiah";
            $cleaned = preg_replace('/\s+/', ' ', $final_result);
        }else{
            $final_result = str_replace("rupiah", "", $hasil);
            $cleaned = preg_replace('/\s+/', ' ', $final_result);
        }

        return $cleaned;
    }
}

// if (!function_exists('calculateRate')) {
//     function calculateRate($nprest, $vlrparc, $vp, $guess = 0.25) {
//         $maxit = 100;
//         $precision = 14;
//         $guess = round($guess,$precision);
//         for ($i=0 ; $i<$maxit ; $i++) {
//             $divdnd = $vlrparc - ( $vlrparc * (pow(1 + $guess , -$nprest)) ) - ($vp * $guess);
//             $divisor = $nprest * $vlrparc * pow(1 + $guess , (-$nprest - 1)) - $vp;
//             $newguess = $guess - ( $divdnd / $divisor );
//             $newguess = round($newguess, $precision);
//             if ($newguess == $guess) {
//                 return $newguess;
//             } else {
//                 $guess = $newguess;
//             }
//         }
//         return null;
//     }
// }

function add_months($date_str, $months) {
    $date = explode('-', $date_str);
    $year = $date[0];
    $month = $date[1];
    $day = $date[2];

    $new_month = $month + $months;
    $new_year = $year + floor($new_month / 12);
    $new_month = $new_month % 12;
    if ($new_month == 0) {
        $new_month = 12;
        $new_year--;
    }

    if (!checkdate($new_month, $day, $new_year)) {
        $lastDay = date('t', mktime(0, 0, 0, $new_month, 1, $new_year));
        return sprintf("%04d-%02d-%02d", $new_year, $new_month, $lastDay);
    } else {
        return sprintf("%04d-%02d-%02d", $new_year, $new_month, $day);
    }
}


if(!function_exists('bilangan')){
    function bilangan($principal,$currency = true) {

        if ($currency){
            $formattedNumber = 'Rp. ' . number_format($principal);
            $principalInWords = strtoupper(angkaKeKata(round($principal, 2)));
        }else{
            $formattedNumber = number_format($principal);
            $principalInWords = strtoupper(angkaKeKata(round($principal, 2),false));
        }
       
        $formattedPrincipal = $formattedNumber . ' (' . $principalInWords . ')';
        return str_replace(' ( ', ' (', $formattedPrincipal);
    }
}

if(!function_exists('converttodecimal')){
    function converttodecimal($number) {
        return floatval(str_replace(',', '',  $number));
    }
}

if(!function_exists('excelRate')){
function excelRate($nper, $pmt, $pv, $fv = 0, $type = 0, $guess = 0.1) {
    $tolerance = 1.0e-15; // Toleransi tinggi untuk presisi
    $maxIterations = 500;

    if ($nper <= 0) {
        return false;
    }

    $rate = $guess;
    for ($i = 0; $i < $maxIterations; $i++) {
        $f = calculateRateEquation($rate, $nper, $pmt, $pv, $fv, $type);
        $df = calculateRateDerivative($rate, $nper, $pmt, $pv, $fv, $type);

        if (abs($df) < $tolerance) {
            return false;
        }

        $newRate = $rate - $f / $df;

        // Cek konvergensi
        if (abs($newRate - $rate) < $tolerance) {
            return ceilToPrecision($newRate, 10);
        }

        $rate = $newRate;
    }

    return false;
}
}

if(!function_exists('calculateRateEquation')){
function calculateRateEquation($rate, $nper, $pmt, $pv, $fv, $type) {
    if (abs($rate) < 1e-15) {
        return $pv + $pmt * $nper + $fv;
    }

    $pow = pow(1 + $rate, $nper);
    return $pv * $pow
         + $pmt * (1 + $rate * $type) * (($pow - 1) / $rate)
         + $fv;
}
}

if(!function_exists('calculateRateDerivative')){
function calculateRateDerivative($rate, $nper, $pmt, $pv, $fv, $type) {
    if (abs($rate) < 1e-15) {
        return $pv * $nper + $pmt * $nper * $type;
    }

    $pow1 = pow(1 + $rate, $nper - 1);
    $pow2 = pow(1 + $rate, $nper);

    return $pv * $nper * $pow1
          + $pmt * $type * $nper * (1 + $rate)
          + $pmt * ($nper * $pow1 - (($pow2 - 1) / ($rate * $rate)));
}
}

if(!function_exists('ceilToPrecision')){
    function ceilToPrecision($number, $precision) {
        $factor = pow(10, $precision);
        return round($number * $factor) / $factor;
    }
}

if(!function_exists('getCustomerDocument')){
    function getCustomerDocument($cust_id, $param) {
        $param = implode(',', array_map(function($type) {
            return "'" . addslashes($type) . "'"; // Escape each type
        }, $param));
        
        $documents = DB::select(
            "   SELECT *
                FROM customer_document AS csd
                WHERE (TYPE, TIMESTAMP) IN (
                    SELECT TYPE, MAX(TIMESTAMP)
                    FROM customer_document
                    WHERE `TYPE` IN ($param)
                        AND CUSTOMER_ID = '$cust_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMESTAMP DESC"
        );

        return $documents;
    }
}

if(!function_exists('parseDate')){
    function parseDatetoYMD($date) {
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        $formattedDate = $dateTime->format('Y-m-d');

        return $formattedDate;
    }
}

if(!function_exists('setPaymentDate')){
    function setPaymentDate($setDate, $monthIncrement = 1) {
        // Convert the input date to a timestamp
        $timestamp = strtotime($setDate);
        
        // Get the day of the month from the input date
        $dayOfMonth = date('d', $timestamp);
        
        // If the day is between 26 and 31, set the date to the 1st of the next month
        if ($dayOfMonth >= 26) {
            $timestamp = strtotime("first day of next month", $timestamp);
        }
    
        // Increment the month by the specified amount
        $newDate = strtotime("+$monthIncrement month", $timestamp);
        
        // Get the last day of the new month
        $newMonthLastDay = date('t', $newDate);
        // Get the current day of the new date
        $newDateDay = date('d', $newDate);
    
        // If the day of the new date is greater than the last day of the new month, set it to the last day of that month
        if ($newDateDay > $newMonthLastDay) {
            $newDate = strtotime("last day of this month", $newDate);
        }
    
        // Return the formatted date
        return date('Y-m-d', $newDate);
    }
}

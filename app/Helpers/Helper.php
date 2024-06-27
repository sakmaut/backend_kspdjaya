<?php

use App\Models\M_Branch;
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
    function generateCode($request, $table, $column) {
        $branchId = $request->user()->branch_id;
        $branch = M_Branch::find($branchId);
    
        if (!$branch) {
            throw new Exception("Branch not found.");
        }
    
        $branchCodeNumber = $branch->CODE_NUMBER;
    
        $latestRecord = DB::table($table)->latest($column)->first();
        $lastSequence = $latestRecord ? (int) substr($latestRecord->$column, 7, 5) + 1 : 1;
    
        $currentDate = Carbon::now();
        $year = $currentDate->format('y');
        $month = $currentDate->format('m');
    
        $generateCode = sprintf("%s%s%s%05d", $branchCodeNumber, $year, $month, $lastSequence);
    
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

if (!function_exists('calculateRate')) {
    function calculateRate($nprest, $vlrparc, $vp, $guess = 0.25) {
        $maxit = 100;
        $precision = 14;
        $guess = round($guess,$precision);
        for ($i=0 ; $i<$maxit ; $i++) {
            $divdnd = $vlrparc - ( $vlrparc * (pow(1 + $guess , -$nprest)) ) - ($vp * $guess);
            $divisor = $nprest * $vlrparc * pow(1 + $guess , (-$nprest - 1)) - $vp;
            $newguess = $guess - ( $divdnd / $divisor );
            $newguess = round($newguess, $precision);
            if ($newguess == $guess) {
                return $newguess;
            } else {
                $guess = $newguess;
            }
        }
        return null;
    }
}

if (!function_exists('generateAmortizationSchedule')) {
   function generateAmortizationSchedule($principal,$angsuran,$annualInterestRate, $loanTerm) {
        $monthlyInterestRate = ($annualInterestRate / 100) / 12;
        // $angsuran_pokok_bunga = round(($principal / $loanTerm) + ($principal * $monthlyInterestRate), 2);
        $angsuran_pokok_bunga =$angsuran;
        $total_bunga = ($principal * $monthlyInterestRate)*$loanTerm;
        $rate = calculateRate($loanTerm, $angsuran_pokok_bunga, $principal);
        $suku_bunga_konversi = round($rate, 10);

        $schedule = [];
        $setDebet = $principal;
        $totalInterest = 0;
        $interestValues = [];

        for ($i = 1; $i <= $loanTerm; $i++) {
            $interest = $setDebet * $suku_bunga_konversi;
            $principalPayment = $angsuran_pokok_bunga - $interest;
            $setDebet -= $principalPayment;
            $pokok = $principalPayment;

            $interestValues[] = $interest;

            if($i == $loanTerm){
                $totalInterest = array_sum(array_slice($interestValues, 0, -1));
                $bnga = round($total_bunga - $totalInterest, 2);
                $angsuran = $pokok + $bnga;
            }else{
                $bnga = round($interest, 2);
                $angsuran = $angsuran_pokok_bunga;
            }
        
            $schedule[] = [
                'angsuran_ke' => $i,
                'pokok' => number_format($pokok,2),
                'bunga' => number_format($bnga,2),
                'total_angsuran' => number_format($angsuran,2),
                'baki_debet' => number_format($setDebet,2)
            ];
        }

        return $schedule;
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

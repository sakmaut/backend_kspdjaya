<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\LocationStatus;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PelunasanController;
use App\Http\Controllers\API\StatusApproval;
use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrApplication;
use App\Models\M_CrApplicationSpouse;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrOrder;
use App\Models\M_CrPersonal;
use App\Models\M_CrPersonalExtra;
use App\Models\M_CrProspect;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use App\Models\M_CustomerExtra;
use App\Models\M_DeuteronomyTransactionLog;
use App\Models\M_FirstArr;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LocationStatus;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use App\Models\M_TelegramBotSend;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

use function Symfony\Component\Mailer\Event\getMessage;

class Welcome extends Controller
{
    public function index(Request $req)
    {

        $return = $this->generateAmortizationSchedule('2025-01-02');
        return response()->json($return,200);
        die;
    }

    private function generateAmortizationSchedule($setDate)
    {
        $schedule = [];
        $totalBunga = 12;

        $remainingBalance = 1000000;
        $term = 6;
        $set_angs = 176666.67;
        $angsuran=ceil(round($set_angs, 3) / 1000) * 1000;

        $flat_rate = excelRate($term, -$angsuran, $remainingBalance);
        $total_bunga = round(($remainingBalance * (12 / 100) / 12) * $term, 2);

        $suku_bunga_konversi = $flat_rate;
        $ttal_bunga = $total_bunga;

        $totalInterestPaid = 0;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($remainingBalance * $suku_bunga_konversi, 2);

            if ($i < $term) {
                $principalPayment = round($angsuran - $interest, 2);
            } else {
                $principalPayment = round($remainingBalance, 2);
                $interest = round($ttal_bunga - $totalInterestPaid, 2);
            }

            $totalPayment = round($principalPayment + $interest, 2);
            $remainingBalance = round($remainingBalance - $principalPayment, 2);
            $totalInterestPaid += $interest;
            if ($i == $term) {
                $remainingBalance = 0.00;
            }

            $schedule[] = [
                'angsuran_ke' => $i,
                'tgl_angsuran' => setPaymentDate($setDate, $i),
                'baki_debet_awal' => floatval($remainingBalance + $principalPayment),
                'pokok' => floatval($principalPayment),
                'bunga' => floatval($interest),
                'total_angsuran' => floatval($totalPayment),
                'baki_debet' => floatval($remainingBalance)
            ];
        }

        return $schedule;
    }
}

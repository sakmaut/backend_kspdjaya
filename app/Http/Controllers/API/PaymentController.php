<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Kwitansi\KwitansiRepository;
use App\Http\Controllers\Repositories\TasksLogging\TasksRepository;
use App\Http\Resources\R_Kwitansi;
use App\Http\Resources\R_PaymentCancelLog;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentApproval;
use App\Models\M_PaymentAttachment;
use App\Models\M_PaymentCancelLog;
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{
    protected $log;
    protected $taskslogging;
    protected $pelunasan;

    public function __construct(ExceptionHandling $log, TasksRepository $taskslogging,PelunasanController $pelunasan)
    {
        $this->log = $log;
        $this->taskslogging = $taskslogging;
        $this->pelunasan = $pelunasan;
    }

    public function index(Request $request)
    {
        try {
            $notrx = $request->query('notrx');
            $nama = $request->query('nama');
            $no_kontrak = $request->query('no_kontrak');
            $tipe = $request->query('tipe');
            $dari = $request->query('dari');

            $getPosition = $request->user()->position;
            $getBranch = $request->user()->branch_id;

            $data = M_Kwitansi::orderBy('CREATED_AT', 'DESC');

            if (strtolower($getPosition) == 'ho') {
                $results = $data->where('STTS_PAYMENT', 'PENDING')->get();
                $dto = R_Kwitansi::collection($results);
                return response()->json($dto, 200);
            }

            $data->where('BRANCH_CODE', $getBranch);

            if ($tipe) {
                $data->where('PAYMENT_TYPE', $tipe == 'pelunasan' ? 'pelunasan' : '!=', 'pelunasan');
            }

            if ($notrx) {
                $data->where('NO_TRANSAKSI', $notrx);
            }

            if ($nama) {
                $data->where('NAMA', 'like', '%' . $nama . '%');
            }

            if ($no_kontrak) {
                $data->where('LOAN_NUMBER', $no_kontrak);
            }

            if ($dari && $dari != 'null') {
                $data->whereDate('CREATED_AT', Carbon::parse($dari)->toDateString());
            } elseif (empty($notrx) && empty($nama) && empty($no_kontrak)) {
                $data->whereDate('CREATED_AT', Carbon::today()->toDateString());
            }

            $results = $data->get();

            $dto = R_Kwitansi::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    private function checkPosition($request)
    {
        $getCurrentPosition = $request->user()->position;

        $setPositionAvailable  = ['mcf', 'kolektor'];
        $checkposition = in_array(strtolower($getCurrentPosition), $setPositionAvailable);

        return $checkposition;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            $check_method_payment = strtolower($request->payment_method) === 'cash';

            $checkposition = $this->checkPosition($request);

            if (isset($request->struktur) && is_array($request->struktur)) {

                foreach ($request->struktur as $res) {

                    if ($res['bayar_angsuran'] != 0 || $res['bayar_denda'] != 0 || strtolower($request->bayar_dengan_diskon) == 'ya') {
                        M_KwitansiStructurDetail::firstOrCreate([
                            'no_invoice' => $no_inv,
                            'key' => $res['key'] ?? '',
                            'loan_number' => $res['loan_number'] ?? ''
                        ], [
                            'angsuran_ke' => $res['angsuran_ke'] ?? '',
                            'tgl_angsuran' => $res['tgl_angsuran'] ?? '',
                            'principal' => $res['principal'] ?? '',
                            'interest' => $res['interest'] ?? '',
                            'installment' => $res['installment'] ?? '',
                            'principal_remains' => $res['principal_remains'] ?? '',
                            'payment' => $res['payment'] ?? '',
                            'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
                            'bayar_denda' => $res['bayar_denda'] ?? '0',
                            'total_bayar' => $res['total_bayar'] ?? '',
                            'flag' => $res['flag'] ?? '',
                            'denda' => $res['denda'] ?? '',
                            'diskon_denda' => strtolower($request->bayar_dengan_diskon) == 'ya' ? 1 : 0
                        ]);
                    }

                    // if ($check_method_payment && strtolower($request->bayar_dengan_diskon) != 'ya' && !$checkposition) {

                    if ($check_method_payment && strtolower($request->bayar_dengan_diskon) != 'ya') {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv);
                    } else {
                        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                        if (($res['bayar_angsuran'] != 0 && $res['installment'] != 0)) {
                            M_CreditSchedule::where([
                                'LOAN_NUMBER' => $res['loan_number'],
                                'PAYMENT_DATE' => $tgl_angsuran
                            ])->update(['PAID_FLAG' => 'PENDING']);
                        }

                        if ($res['bayar_denda'] != 0 || (isset($res['diskon_denda']) && $res['diskon_denda'] == 1) || strtolower($request->bayar_dengan_diskon) == 'ya') {
                            M_Arrears::where([
                                'LOAN_NUMBER' => $res['loan_number'],
                                'START_DATE' => $tgl_angsuran,
                                'STATUS_REC' => 'A'
                            ])->update(['STATUS_REC' => 'PENDING']);
                        }
                    }
                }
            }

            $this->saveKwitansi($request, $no_inv);

            $data = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            $message = "A/n ".$data->NAMA." Nominal ".number_format($data->JUMLAH_UANG);

            if (!$check_method_payment) {
                $this->taskslogging->create($request,'Pembayaran Transfer', 'payment', $no_inv, 'PENDING', "Transfer ".$message);
            } elseif (strtolower($request->bayar_dengan_diskon) == 'ya') {
                $this->taskslogging->create($request,'Permintaan Diskon', 'request_discount', $no_inv, 'PENDING', "Permintaan Diskon ".$message);
            }
            // } elseif ($checkposition) {
            //     $this->taskslogging->create($request,'Pembayaran Cash (Mcf/Kolektor)', 'request_payment', $no_inv, 'PENDING', "Pembayaran Cash (Mcf/Kolektor) ".$message);
            // }

            $dto = new R_Kwitansi($data);

            DB::commit();
            return response()->json($dto, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $checkKwitansi = M_Kwitansi::where('NO_TRANSAKSI', $id)->first();

            if (!$checkKwitansi) {
                throw new Exception("Kwitansi Not Found", 404);
            }

            $dto = new R_Kwitansi($checkKwitansi);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    private function processPaymentStructure($res, $request, $getCodeBranch, $no_inv)
    {
        $loan_number = $res['loan_number'];
        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
        $uid = Uuid::uuid7()->toString();

        $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid);

        if ((strtolower($request->bayar_dengan_diskon) == 'ya' && isset($request->bayar_dengan_diskon) && $request->bayar_dengan_diskon != '') || isset($res['diskon_denda']) && $res['diskon_denda'] == 1) {
            $this->updateDiscountArrears($loan_number, $tgl_angsuran, $res, $uid);
        } else {
            $this->updateArrears($loan_number, $tgl_angsuran, $res, $uid);
        }

        if ($res['bayar_angsuran'] != 0 || $res['bayar_denda'] != 0 || (isset($res['diskon_denda']) && $res['diskon_denda'] == 1)) {
            $this->createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $getCodeBranch, $uid);
        }

        $this->updateCredit($loan_number);
    }

    private function updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $flag = $res['flag'];

        if ($credit_schedule || $byr_angsuran != 0 || $flag != 'PAID') {

            $payment_value = floatval($byr_angsuran) + floatval($credit_schedule->PAYMENT_VALUE);

            $valBeforePrincipal = floatval($credit_schedule->PAYMENT_VALUE_PRINCIPAL);
            $valBeforeInterest = floatval($credit_schedule->PAYMENT_VALUE_INTEREST);
            $getPrincipal = floatval($credit_schedule->PRINCIPAL);
            $getInterest = floatval($credit_schedule->INTEREST);

            $new_payment_value_principal = floatval($valBeforePrincipal);
            $new_payment_value_interest = floatval($valBeforeInterest);

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = floatval($getPrincipal) - floatval($valBeforePrincipal);

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + floatval($remaining_payment), $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;

                $valPrincipal = $new_payment_value_principal - $valBeforePrincipal;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $valPrincipal);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $valPrincipal]);
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;

                $valInterest = $new_payment_value_interest - $valBeforeInterest;
                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
            }

            $total_paid = floatval($new_payment_value_principal) + floatval($new_payment_value_interest);

            $insufficient_payment = ($getPrincipal > $new_payment_value_principal || $getInterest > $new_payment_value_interest)
                ? ($total_paid - $credit_schedule->INSTALLMENT)
                : 0;

            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;
            $updates['PAYMENT_VALUE'] = $payment_value;

            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            $credit_schedule->update(['PAID_FLAG' => $credit_schedule->PAYMENT_VALUE >= $credit_schedule->INSTALLMENT ? 'PAID' : '']);
        }
    }

    private function updateCredit($loan_number)
    {
        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        $isActive = $this->checkStatusCreditActive($loan_number);

        $statusData = [
            'STATUS' => $isActive == 0 ? 'D' : 'A',
            'STATUS_REC' => $isActive == 0 ? 'CL' : 'AC'
        ];

        $check_credit->update($statusData);
    }

    private function checkStatusCreditActive($loan_number)
    {
        $results = DB::table('credit_schedule as a')
            ->leftJoin('arrears as b', function ($join) {
                $join->on('b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                    ->on('b.START_DATE', '=', 'a.PAYMENT_DATE');
            })
            ->select('a.ID', 'a.PAYMENT_DATE', 'a.PAID_FLAG', 'b.STATUS_REC')
            ->where('a.LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->whereNull('a.PAID_FLAG')
                    ->orWhere('a.PAID_FLAG', '')
                    ->orWhereIn('b.STATUS_REC', ['A', 'PENDING']);
            })
            ->orderBy('a.INSTALLMENT_COUNT', 'asc')
            ->get();

        if ($results->isEmpty()) {
            $resultStatus = 0;
        } else {
            $resultStatus = 1;
        }

        return $resultStatus;
    }

    private function updateDiscountArrears($loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->orderBy('START_DATE', 'ASC')->first();

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $bayar_denda = floatval($res['bayar_denda']);

        if ($check_arrears) {
            $valBeforePrincipal = floatval($check_arrears->PAID_PCPL);
            $valBeforeInterest = floatval($check_arrears->PAID_INT);
            $getPrincipal = floatval($check_arrears->PAST_DUE_PCPL);
            $getInterest = floatval($check_arrears->PAST_DUE_INTRST);
            $getPenalty = floatval($check_arrears->PAST_DUE_PENALTY);

            $new_payment_value_principal = floatval($valBeforePrincipal);
            $new_payment_value_interest = floatval($valBeforeInterest);

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + floatval($remaining_payment), $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $paymentData = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($paymentData);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);


            $remainingPenalty = floatval($getPenalty) - floatval($bayar_denda);
            if ($remainingPenalty > 0) {
                $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $remainingPenalty);
                M_PaymentDetail::create($discountPaymentData);
                $this->addCreditPaid($loan_number, ['DISKON_DENDA' => $remainingPenalty]);
                $updates['WOFF_PENALTY'] = $remainingPenalty;
            }

            $updates['PAID_PENALTY'] = $bayar_denda;
            $updates['END_DATE'] = now();
            $updates['UPDATED_AT'] = now();
            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $check_arrears->update(['STATUS_REC' => $remainingPenalty > 0 ? 'D' : 'S']);
        }
    }

    private function updateArrears($loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->orderBy('START_DATE', 'ASC')->first();

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $bayar_denda = floatval($res['bayar_denda']);

        if ($check_arrears || $res['bayar_denda'] != 0) {
            $current_penalty = $check_arrears->PAID_PENALTY;

            $new_penalty = floatval($current_penalty) + floatval($bayar_denda);

            $valBeforePrincipal = floatval($check_arrears->PAID_PCP);
            $valBeforeInterest = floatval($check_arrears->PAID_INT);
            $getPrincipal = floatval($check_arrears->PAST_DUE_PCPL);
            $getInterest = floatval($check_arrears->PAST_DUE_INTRST);

            $new_payment_value_principal = floatval($valBeforePrincipal);
            $new_payment_value_interest = floatval($valBeforeInterest);

            if ($valBeforePrincipal < $getPrincipal) {
                $remaining_to_principal = $getPrincipal - $valBeforePrincipal;

                if ($byr_angsuran >= $remaining_to_principal) {
                    $new_payment_value_principal = $getPrincipal;
                    $remaining_payment = $byr_angsuran - $remaining_to_principal;
                } else {
                    $new_payment_value_principal += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            if ($new_payment_value_principal == $getPrincipal) {
                if ($valBeforeInterest < $getInterest) {
                    $new_payment_value_interest = min($valBeforeInterest + floatval($remaining_payment), $getInterest);
                }
            }

            $updates = [];
            if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
            }

            if ($new_payment_value_interest !== $valBeforeInterest) {
                $updates['PAID_INT'] = $new_payment_value_interest;
            }

            $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($data);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $updates['PAID_PENALTY'] = $new_penalty;
            $updates['END_DATE'] = now();
            $updates['UPDATED_AT'] = now();
            $updates['STATUS_REC'] = 'A';

            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $checkFlag = $this->checkArrearsBalance($loan_number, $tgl_angsuran);

            if ($checkFlag != null && $checkFlag != 0) {
                $check_arrears->update(['STATUS_REC' => 'S']);
            }
        }
    }

    public function checkArrearsBalance($loan_number, $setDate)
    {
        $checkFlag = DB::table('arrears')
            ->selectRaw('
            CASE 
                WHEN SUM(COALESCE(PAST_DUE_PCPL, 0) + COALESCE(PAST_DUE_INTRST, 0) + COALESCE(PAST_DUE_PENALTY, 0)) 
                     = SUM(COALESCE(PAID_PCPL, 0) + COALESCE(PAID_INT, 0) + COALESCE(PAID_PENALTY, 0)) 
                THEN 1 
                ELSE 0 
            END AS check_flag
        ')
            ->where('LOAN_NUMBER', $loan_number)
            ->where('START_DATE', $setDate)
            ->first();

        if ($checkFlag) {
            return $checkFlag->check_flag;
        }

        return null;
    }

    private function saveKwitansi($request, $no_inv)
    {
        $getCustomer = M_Credit::with('customer')->where('LOAN_NUMBER', $request->no_facility)->first();

        if(!$getCustomer){
            throw new Exception("Customer Not Found", 404);
        }

        $cekPaymentMethod = $request->payment_method == 'cash' && strtolower($request->bayar_dengan_diskon) != 'ya';

        //  "STTS_PAYMENT" => $cekPaymentMethod && !$this->checkPosition($request) ? "PAID" : "PENDING",

        $save_kwitansi = [
            "PAYMENT_TYPE" => 'angsuran',
            "PAYMENT_ID" => $request->uid,
            "STTS_PAYMENT" => $cekPaymentMethod ? "PAID" : "PENDING",
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->no_facility ?? null,
            "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
            'BRANCH_CODE' => $request->user()->branch_id,
            'CUST_CODE' => $getCustomer->customer['CUST_CODE'] ?? '',
            'NAMA' => $getCustomer->customer['NAME'] ?? '',
            'ALAMAT ' => $getCustomer->customer['ADDRESS'] ?? '',
            'RT' => $getCustomer->customer['RT'] ?? '',
            'RW ' => $getCustomer->customer['RW'] ?? '',
            'PROVINSI' => $getCustomer->customer['PROVINCE'] ?? '',
            'KOTA ' => $getCustomer->customer['CITY'] ?? '',
            'KELURAHAN' => $getCustomer->customer['KELURAHAN'] ?? '',
            'KECAMATAN ' => $getCustomer->customer['KECAMATAN'] ?? '',
            "METODE_PEMBAYARAN" => $request->payment_method ?? null,
            "TOTAL_BAYAR" => $request->total_bayar ?? null,
            "DISKON" => $request->diskon_tunggakan ?? null,
            "DISKON_FLAG" => $request->bayar_dengan_diskon ?? null,
            "PEMBULATAN" => $request->pembulatan ?? null,
            "KEMBALIAN" => $request->kembalian ?? null,
            "JUMLAH_UANG" => $request->jumlah_uang ?? null,
            "NAMA_BANK" => $request->nama_bank ?? null,
            "NO_REKENING" => $request->no_rekening ?? null,
            "CREATED_BY" => $request->user()->id,
            "CREATED_AT" => Carbon::now()
        ];

        M_Kwitansi::firstOrCreate(
            ['NO_TRANSAKSI' => $no_inv],
            $save_kwitansi
        );
    }

    function createPaymentRecords($request, $res, $tgl_angsuran, $loan_number, $no_inv, $branch, $uid)
    {
        $kwitansi = M_Kwitansi::where(['NO_TRANSAKSI' => $no_inv])->first();

        if ($kwitansi) {
            $user_id = $kwitansi->CREATED_BY;
            $getCodeBranch = M_Branch::find($kwitansi->BRANCH_CODE);
        }

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => $res['flag'] == 'PAID' ? 'angsuran_denda' : $request->pembayaran ?? '',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid,
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? $request->payment_method,
            'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? $branch->CODE_NUMBER,
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => (float)($res['bayar_angsuran']) + (float)($res['bayar_denda']),
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran,
            'END_DATE' => now(),
            'USER_ID' => $user_id ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? ''
        ];

        $existing = M_Payment::where($paymentData)->first();

        if (!$existing) {
            $paymentData['BANK_NAME'] = round(microtime(true) * 1000);
            M_Payment::create($paymentData);
        }
    }

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    public function addCreditPaid($loan_number, array $data)
    {
        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($check_credit) {
            $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK'] : 0;
            $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] : 0;
            $paidPenalty = isset($data['BAYAR_DENDA']) ? $data['BAYAR_DENDA'] : 0;

            $check_credit->update([
                'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
                'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
                'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty)
            ]);
        }
    }

    public function destroyImage(Request $req, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_PaymentAttachment::findOrFail($id);

            $check->delete();

            DB::commit();
            ActivityLogger::logActivity($req, "deleted successfully", 200);
            return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Document Id Not Found', 404);
            return response()->json(['message' => 'Document Id Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function upload(Request $req)
    {
        DB::beginTransaction();
        try {

            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Payment/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . 'Payment/' . $fileName;

                $uidGnerate = Uuid::uuid7()->toString();

                // Prepare data for database insertion
                $data_array_attachment = [
                    'id' => Uuid::uuid4()->toString(),
                    'payment_id' => $req->uid ?? $uidGnerate,
                    'file_attach' => $url ?? '',
                    'create_by' => $req->user()->id ?? '',
                    'create_position' => $req->user()->position ?? '',
                    'create_branch' => $req->user()->branch_id ?? '',
                    'create_date' => Carbon::now()
                ];

                $check = M_PaymentAttachment::where('payment_id', $req->uid)->first();

                if ($check) {
                    $check->delete();
                }

                M_PaymentAttachment::create($data_array_attachment);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function approval(Request $request)
    {
        DB::beginTransaction();
        try {

            $getInvoice = $request->no_invoice;
            $getFlag = $request->flag == 'yes' ? 'PAID' : 'CANCEL';

            $kwitansi = M_Kwitansi::where(['NO_TRANSAKSI' => $getInvoice, 'STTS_PAYMENT' => 'PENDING'])->lockForUpdate()->first();

            if ($kwitansi) {
                $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

                $request->merge(['payment_method' => 'transfer']);

                if ($request->flag == 'yes') {

                    if ($kwitansi->PAYMENT_TYPE === 'pelunasan') {
                        $this->pelunasan->proccess($request, $kwitansi->LOAN_NUMBER, $getInvoice, 'PAID');
                    } else {
                        $getKwitansiDetail = M_KwitansiStructurDetail::where([
                            'no_invoice' => $getInvoice
                        ])->orderBy('angsuran_ke', 'asc')->get();

                        if ($getKwitansiDetail->isEmpty()) {
                            throw new Exception("Kwitansi Detail Not Found", 404);
                        }

                        foreach ($getKwitansiDetail as $res) {
                            $request->merge(['approval' => 'approve', 'pembayaran' => $res['bayar_denda'] != 0 ? 'angsuran_denda' : 'angsuran']);
                            $this->processPaymentStructure($res, $request, $getCodeBranch, $getInvoice);
                        }
                    }

                    $kwitansi->update(['STTS_PAYMENT' => 'PAID']);
                } else {
                    $request->merge(['approval' => 'no']);

                    if ($kwitansi->PAYMENT_TYPE === 'pelunasan') {
                        $this->pelunasan->proccessCancel($kwitansi->LOAN_NUMBER, $getInvoice, 'CANCEL');
                    } else {

                        if (isset($request->struktur) && is_array($request->struktur)) {
                            foreach ($request->struktur as $res) {
                                $loan_number = $res['loan_number'];
                                $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
                                $uid = Uuid::uuid7()->toString();

                                M_Payment::create([
                                    'ID' => $uid,
                                    'ACC_KEY' =>  $res['bayar_denda'] != 0 ? 'angsuran_denda' : 'angsuran',
                                    'STTS_RCRD' => 'CANCEL',
                                    'INVOICE' => $getInvoice ?? '',
                                    'NO_TRX' => $getInvoice ?? '',
                                    'PAYMENT_METHOD' => 'transfer',
                                    'BRANCH' => $getCodeBranch->CODE_NUMBER ?? '',
                                    'LOAN_NUM' => $loan_number ?? '',
                                    'ENTRY_DATE' => now(),
                                    'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
                                    'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
                                    'OS_AMOUNT' => $os_amount ?? 0,
                                    'START_DATE' => $tgl_angsuran ?? '',
                                    'END_DATE' => now(),
                                    'USER_ID' => $request->user()->id ?? '',
                                    'AUTH_BY' => $request->user()->fullname ?? '',
                                    'AUTH_DATE' => now(),
                                    'ARREARS_ID' => $res['id_arrear'] ?? '',
                                    'BANK_NAME' => round(microtime(true) * 1000)
                                ]);

                                if (($res['installment'] != 0)) {
                                    $credit_schedule = M_CreditSchedule::where([
                                        'LOAN_NUMBER' => $loan_number,
                                        'PAYMENT_DATE' => $tgl_angsuran
                                    ])->where(function ($query) {
                                        $query->where('PAID_FLAG', '!=', 'PAID')
                                            ->orWhereNull('PAID_FLAG');
                                    })->first();

                                    $credit_schedule->update(['PAID_FLAG' => '']);
                                }

                                $today = date('Y-m-d');
                                $daysDiff = (strtotime($today) - strtotime($tgl_angsuran)) / (60 * 60 * 24);
                                $pastDuePenalty = $res['installment'] ?? 0 * ($daysDiff * 0.005);

                                M_Arrears::where([
                                    'LOAN_NUMBER' => $loan_number,
                                    'START_DATE' => $tgl_angsuran
                                ])->update([
                                    'STATUS_REC' => 'A',
                                    'PAST_DUE_PENALTY' => $pastDuePenalty ?? 0,
                                    'UPDATED_AT' => Carbon::now()
                                ]);
                            }
                        }

                        $kwitansi->update(['STTS_PAYMENT' => 'CANCEL']);
                    }
                }

                if($kwitansi->PAYMENT_TYPE === 'pelunasan'){
                    $type= "Pelunasan";
                }else{
                    $type = "Angsuran";
                }

                if($request->flag == 'yes'){
                    $title = $type." Disetujui";
                }else{
                    $title = $type." Ditolak";
                }

                $this->taskslogging->create($request,$title ,'payment', $getInvoice, $getFlag, $request->keterangan);

                $data_approval = [
                    'PAYMENT_ID' => $request->no_invoice,
                    'ONCHARGE_APPRVL' => $request->flag,
                    'ONCHARGE_PERSON' => $request->user()->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_DESCR' => $request->keterangan,
                    'APPROVAL_RESULT' => $request->flag == 'yes' ? 'PAID' : 'CANCEL'
                ];

                M_PaymentApproval::create($data_approval);
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function cancel(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'no_invoice' => 'required|string',
                'flag' => 'in:yes,no',
            ]);

            $no_invoice = $request->no_invoice;
            $flag = $request->flag;

            $check = M_Kwitansi::where([
                'NO_TRANSAKSI' => $no_invoice,
                'STTS_PAYMENT' => 'PAID'
            ])->lockForUpdate()->first();

            if (!$check) {
                throw new Exception("Kwitansi Number Not Exist", 404);
            }

            $check->update(['STTS_PAYMENT' => 'WAITING CANCEL']);

            $setTitle = "Pembatalan Pembayaran";
            $message = "A/n " . $check->NAMA . " Nominal " . number_format($check->JUMLAH_UANG);
            $this->taskslogging->create($request, $setTitle, 'payment_cancel', $no_invoice, 'WAITING CANCEL', "Menunggu ". $setTitle.' '. $message);

            $checkPaymentLog = M_PaymentCancelLog::where('INVOICE_NUMBER', $no_invoice)->first();

            if (!$checkPaymentLog) {
                M_PaymentCancelLog::create([
                    'INVOICE_NUMBER' => $no_invoice ?? '',
                    'REQUEST_BY' => $request->user()->id ?? '',
                    'REQUEST_BRANCH' => $request->user()->branch_id ?? '',
                    'REQUEST_POSITION' => $request->user()->position ?? '',
                    'REQUEST_DESCR' => $request->descr ?? '',
                    'REQUEST_DATE' => Carbon::now()
                ]);
            }

            if (strtolower($request->user()->position) == 'ho' && isset($flag) && !empty($flag)) {

                if ($check->PAYMENT_TYPE === 'pelunasan') {
                    $type = "repayment_cancel";
                } else {
                    $type = "payment_cancel";
                }

                if ($flag == 'yes') {
                    $title = $setTitle . " Disetujui";
                    $status = "APPROVE";
                } else {
                    $title = $setTitle . " Ditolak";
                    $status ="REJECTED";
                }

                $this->taskslogging->create($request, $title, $type, $no_invoice, $status, $title." " . $message." ". $request->keterangan ?? '');

                $this->processHoApproval($request, $check);
            }

            DB::commit();
            return response()->json(['message' => "Invoice Number {$no_invoice} Cancel Success"], 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    private function processHoApproval(Request $request, $check)
    {
        if (strtolower($request->flag) === 'yes') {

            if ($check->PAYMENT_TYPE === 'pelunasan') {
                $this->pelunasan->proccessCancel($check->LOAN_NUMBER, $request->no_invoice, 'CANCEL');
            } else {
                $check->update([
                    'STTS_PAYMENT' => 'CANCEL'
                ]);

                $checkPayment = M_Payment::where(['INVOICE' => $request->no_invoice])->get();

                if (!empty($checkPayment)) {
                    foreach ($checkPayment as $list) {
                        $list->update(['STTS_RCRD' => 'CANCEL', 'AUTH_BY' => $request->user()->fullname ?? '', 'AUTH_DATE' => Carbon::now()]);
                    }
                }

                $loan_number = $request->value['loan_number'];
                $totalPrincipal = $request->value['totalPrincipal'];
                $totalInterest = $request->value['totalInterest'];
                $totalPaidPenalty = $request->value['totalPaidPenalty'];
                $creditSchedule = $request->value['creditSchedule'];

                $setPrincipal = round($totalPrincipal, 2);

                $creditCheck = M_Credit::where('LOAN_NUMBER', $loan_number)
                    ->whereIn('STATUS', ['A', 'D'])
                    ->first();

                if ($creditCheck) {
                    $creditCheck->update([
                        'STATUS_REC' => 'AC',
                        'STATUS' => 'A',
                        'PAID_PRINCIPAL' => floatval($creditCheck->PAID_PRINCIPAL) - floatval($setPrincipal ?? 0),
                        'PAID_INTEREST' => floatval($creditCheck->PAID_INTEREST ?? 0) - floatval($totalInterest ?? 0),
                        'PAID_PENALTY' => floatval($creditCheck->PAID_PENALTY ?? 0) - floatval($totalPaidPenalty ?? 0),
                        'MOD_USER' => $request->user()->id,
                        'MOD_DATE' => Carbon::now(),
                    ]);
                }

                if (!empty($creditSchedule)) {
                    foreach ($creditSchedule as $resList) {
                        $creditScheduleCheck = M_CreditSchedule::where([
                            'LOAN_NUMBER' => $loan_number,
                            'PAYMENT_DATE' => $resList['PAYMENT_DATE']
                        ])->first();

                        if ($creditScheduleCheck) {
                            $creditScheduleCheck->update([
                                'PAYMENT_VALUE_PRINCIPAL' => $resList['PRINCIPAL'] != 0 ? floatval($creditScheduleCheck->PAYMENT_VALUE_PRINCIPAL ?? 0) - floatval($resList['PRINCIPAL'] ?? 0) : floatval($creditScheduleCheck->PAYMENT_VALUE_PRINCIPAL ?? 0),
                                'PAYMENT_VALUE_INTEREST' => $resList['INTEREST'] != 0 ? floatval($creditScheduleCheck->PAYMENT_VALUE_INTEREST ?? 0) - floatval($resList['INTEREST'] ?? 0) : floatval($creditScheduleCheck->PAYMENT_VALUE_INTEREST ?? 0),
                                'INSUFFICIENT_PAYMENT' => $resList['AMOUNT'] != 0 ? ((floatval($resList['PRINCIPAL'] ?? 0) + floatval($resList['INTEREST'] ?? 0)) - $creditScheduleCheck->INSTALLMENT ?? 0) - floatval($creditScheduleCheck->INSUFFICIENT_PAYMENT ?? 0) : floatval($creditScheduleCheck->INSUFFICIENT_PAYMENT ?? 0),
                                'PAYMENT_VALUE' => $resList['AMOUNT'] != 0 ? floatval($creditScheduleCheck->PAYMENT_VALUE ?? 0) - floatval($resList['AMOUNT'] ?? 0) : floatval($creditScheduleCheck->PAYMENT_VALUE ?? 0),
                                'PAID_FLAG' => $resList['PRINCIPAL'] == 0 && $resList['INTEREST'] == 0 ? 'PAID' : ''
                            ]);
                        }

                        $arrearsCheck = M_Arrears::where([
                            'LOAN_NUMBER' => $loan_number,
                            'START_DATE' => $resList['PAYMENT_DATE']
                        ])->first();

                        if ($arrearsCheck) {
                            $arrearsCheck->update([
                                'PAID_PCPL' => $resList['PRINCIPAL'] != 0 ? floatval($arrearsCheck->PAID_PCPL ?? 0) - floatval($resList['PRINCIPAL'] ?? 0) : floatval($arrearsCheck->PAID_PCPL ?? 0),
                                'PAID_INT' => $resList['INTEREST'] != 0 ? floatval($arrearsCheck->PAID_INT ?? 0) - floatval($resList['INTEREST'] ?? 0) : floatval($arrearsCheck->PAID_INT ?? 0),
                                'PAID_PENALTY' => $resList['PENALTY'] != 0 ? floatval($arrearsCheck->PAID_PENALTY ?? 0) - floatval($resList['PENALTY'] ?? 0) : floatval($arrearsCheck->PAID_PENALTY ?? 0),
                            ]);

                            $setStatus = $arrearsCheck->PAST_DUE_PCPL == $arrearsCheck->PAID_PCPL &&
                                $arrearsCheck->PAST_DUE_INTRST == $arrearsCheck->PAID_INT &&
                                $arrearsCheck->PAST_DUE_PENALTY == $arrearsCheck->PAID_PENALTY;

                            $arrearsCheck->update([
                                'STATUS_REC' => $setStatus ? 'S' : 'A',
                            ]);
                        }
                    }
                }
            }
        }

        // Update cancellation log
        $checkCreditCancel = M_PaymentCancelLog::where('INVOICE_NUMBER', $request->no_invoice)->first();
        if ($checkCreditCancel) {
            $checkCreditCancel->update([
                'ONCHARGE_DESCR' => $request->keterangan ?? '',
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'ONCHARGE_FLAG' => $request->flag ?? '',
            ]);
        }
    }

    public function cancelList(Request $request)
    {
        try {
            $data = DB::table('payment_cancel_log as a')
                ->select(
                    'a.ID',
                    'a.INVOICE_NUMBER',
                    'a.REQUEST_BY',
                    'a.REQUEST_BRANCH',
                    'a.REQUEST_POSITION',
                    'a.REQUEST_DATE',
                    'a.ONCHARGE_PERSON',
                    'a.ONCHARGE_TIME',
                    'a.ONCHARGE_DESCR',
                    'a.ONCHARGE_FLAG',
                    'b.LOAN_NUMBER',
                    'b.TGL_TRANSAKSI'
                )
                ->leftJoin('kwitansi as b', 'b.NO_TRANSAKSI', '=', 'a.INVOICE_NUMBER')
                ->where(function ($query) {
                    $query->whereNull('a.ONCHARGE_PERSON')
                        ->orWhere('a.ONCHARGE_PERSON', '');
                })
                ->where(function ($query) {
                    $query->whereNull('a.ONCHARGE_TIME')
                        ->orWhere('a.ONCHARGE_TIME', '');
                })
                ->get();

            $dto = R_PaymentCancelLog::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

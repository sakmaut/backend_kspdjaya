<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\TasksLogging\TasksRepository;
use App\Http\Resources\R_Kwitansi;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
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

    public function __construct(ExceptionHandling $log, TasksRepository $taskslogging, PelunasanController $pelunasan)
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
                $results = $data->where('STTS_PAYMENT', 'PENDING')
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            $q->where('METODE_PEMBAYARAN', '!=', 'cash')
                                ->where('PAYMENT_TYPE', 'angsuran');
                        })->orWhere(function ($q) {
                            $q->where('METODE_PEMBAYARAN', 'cash')
                                ->where('PAYMENT_TYPE', 'pelunasan');
                        });
                    })->get();

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
                            'principal_prev' => $res['principal_prev'] ?? 0,
                            'interest_prev' => $res['interest_prev'] ?? 0,
                            'insuficient_payment_prev' => $res['insuficient_payment_prev'] ?? 0,
                            'payment' => $res['payment'] ?? '',
                            'bayar_angsuran' => $res['bayar_angsuran'] ?? '',
                            'bayar_denda' => $res['bayar_denda'] ?? '0',
                            'total_bayar' => $res['total_bayar'] ?? '',
                            'flag' => $res['flag'] ?? '',
                            'denda' => $res['denda'] ?? '',
                            'diskon_denda' => strtolower($request->bayar_dengan_diskon) == 'ya' ? floatval($res['denda']) - floatval($res['bayar_denda']) : 0
                        ]);
                    }

                    // if ($check_method_payment && strtolower($request->bayar_dengan_diskon) != 'ya') {

                    if ($check_method_payment && strtolower($request->bayar_dengan_diskon) != 'ya' && !$checkposition) {
                        $this->processPaymentStructure($res, $request, $getCodeBranch, $no_inv);
                    } else {
                        $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                        if (($res['bayar_angsuran'] != 0 && $res['installment'] != 0)) {
                            M_CreditSchedule::where([
                                'LOAN_NUMBER' => $res['loan_number'],
                                'PAYMENT_DATE' => $tgl_angsuran
                            ])->update(['PAID_FLAG' => 'PENDING']);
                        }

                        if ($res['bayar_denda'] != 0 || strtolower($request->bayar_dengan_diskon) == 'ya') {
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

            $data = M_Kwitansi::with('users')->where('NO_TRANSAKSI', $no_inv)->first();

            $message = "A/n " . $data->NAMA . " Nominal " . number_format($data->JUMLAH_UANG);

            if (!$check_method_payment) {
                $this->taskslogging->create($request, 'Pembayaran Transfer', 'payment', $no_inv, 'PENDING', "Transfer " . $message);
            } elseif (strtolower($request->bayar_dengan_diskon) == 'ya') {
                $this->taskslogging->create($request, 'Permintaan Diskon', 'request_discount', $no_inv, 'PENDING', "Permintaan Diskon " . $message);
            } elseif ($checkposition) {
                $this->taskslogging->create($request, 'Pembayaran Cash ' . $data->users->fullname ?? '', 'request_payment', $no_inv, 'PENDING', "Pembayaran Cash " . $message);
            }

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

        $credit = M_Credit::where('LOAN_NUMBER', $request->no_facility)->first();

        if ($credit->CREDIT_TYPE != 'bunga_menurun') {
            $this->updateCreditSchedule($loan_number, $tgl_angsuran, $res, $uid);
        } else {
            $this->updateCreditScheduleBungaMenurun($loan_number, $tgl_angsuran, $res, $uid);
        }

        if ((strtolower($request->diskon_flag) == 'ya' && isset($request->diskon_flag) && $request->diskon_flag != '')) {
            $this->updateDiscountArrears($loan_number, $tgl_angsuran, $res, $uid);
        } else {
            $this->updateArrears($loan_number, $tgl_angsuran, $res, $uid);
        }

        if ($res['bayar_angsuran'] != 0 || $res['bayar_denda'] != 0 || strtolower($request->bayar_dengan_diskon) != 'ya') {
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

    private function updateCreditScheduleBungaMenurun($loan_number, $tgl_angsuran, $res, $uid)
    {
        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        if (!$credit_schedule) return;

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $flag = $res['flag'];

        $current_paid_interest = floatval($credit_schedule->PAYMENT_VALUE_INTEREST);
        $total_interest = floatval($credit_schedule->INTEREST);

        if ($byr_angsuran != 0 || $flag != 'PAID') {

            $new_payment_value_interest = $current_paid_interest;

            $remaining_to_interest = $total_interest - $current_paid_interest;
            $remaining_payment = 0;

            if ($remaining_to_interest > 0) {
                if ($byr_angsuran >= $remaining_to_interest) {
                    $new_payment_value_interest = $total_interest;
                    $remaining_payment = $byr_angsuran - $remaining_to_interest;
                } else {
                    $new_payment_value_interest += $byr_angsuran;
                    $remaining_payment = 0;
                }
            } else {
                $remaining_payment = $byr_angsuran;
            }

            $updates = [];
            if ($new_payment_value_interest != $current_paid_interest) {
                $valInterest = $new_payment_value_interest - $current_paid_interest;

                $updates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;

                $data = $this->preparePaymentData($uid, 'ANGSURAN_BUNGA', $valInterest);
                M_PaymentDetail::create($data);
                $this->addCreditPaid($loan_number, ['ANGSURAN_BUNGA' => $valInterest]);
            }

            $current_payment_value = floatval($credit_schedule->PAYMENT_VALUE);
            $installment = floatval($credit_schedule->INSTALLMENT);

            if ($current_payment_value < $installment) {
                $remaining_payment = $installment - $current_payment_value;
                $additional_payment = min($byr_angsuran, $remaining_payment);
                $payment_value = $current_payment_value + $additional_payment;

                $updates['PAYMENT_VALUE'] = $payment_value;
            }

            $insufficient_payment = max(0, $total_interest - $new_payment_value_interest);

            $updates['INSUFFICIENT_PAYMENT'] = $insufficient_payment;

            if (!empty($updates)) {
                $credit_schedule->update($updates);
            }

            if ($new_payment_value_interest >= $total_interest) {
                $credit_schedule->update(['PAID_FLAG' => 'PAID']);
            }
        }
    }

    private function updateDiscountArrears($loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $bayar_denda = floatval($res['bayar_denda']);
        $diskon_denda = floatval($res['diskon_denda']);

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
            $updates['PAID_PENALTY'] = $bayar_denda;

            $checkDiskonDenda = $diskon_denda > 0;

            if ($checkDiskonDenda) {
                $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $diskon_denda);
                M_PaymentDetail::create($discountPaymentData);
                $this->addCreditPaid($loan_number, ['DISKON_DENDA' => $diskon_denda]);
                $updates['WOFF_PENALTY'] = $diskon_denda;
            }

            $updates['END_DATE'] = now();
            $updates['UPDATED_AT'] = now();

            if (!empty($updates)) {
                $check_arrears->update($updates);
            }

            $check_arrears->update(['STATUS_REC' => $checkDiskonDenda ? 'D' : 'S']);
        }
    }

    private function updateArrears($loan_number, $tgl_angsuran, $res, $uid)
    {
        $check_arrears = M_Arrears::where([
            'LOAN_NUMBER' => $loan_number,
            'START_DATE' => $tgl_angsuran
        ])->first();

        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'PAYMENT_DATE' => $tgl_angsuran
        ])->first();

        $byr_angsuran = floatval($res['bayar_angsuran']);
        $bayar_denda = floatval($res['bayar_denda']);

        if ($check_arrears || $res['bayar_denda'] != 0) {
            $current_penalty = $check_arrears->PAID_PENALTY ?? 0;

            $new_penalty = floatval($current_penalty) + floatval($bayar_denda);

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

            $data = $this->preparePaymentData($uid, 'BAYAR_DENDA', $bayar_denda);
            M_PaymentDetail::create($data);
            $this->addCreditPaid($loan_number, ['BAYAR_DENDA' => $bayar_denda]);

            $updates['PAID_PENALTY'] = $new_penalty;
            $updates['STATUS_REC'] = bccomp($getPenalty, $new_penalty, 2) === 0 && $credit_schedule->PAID_FLAG == 'PAID' ? 'S' : 'A';
            $updates['END_DATE'] = now();
            $updates['UPDATED_AT'] = now();

            if (!empty($updates)) {
                $check_arrears->update($updates);
            }
        }
    }

    private function updateCredit($loan_number)
    {
        $credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($credit) {
            $isActive = $this->checkStatusCreditActive($loan_number);

            if ($isActive == 0) {
                $statusData = [
                    'STATUS' => 'D',
                    'STATUS_REC' => 'CL'
                ];
            } else {
                $statusData = [
                    'STATUS' => 'A',
                    'STATUS_REC' => 'AC'
                ];
            }

            $credit->update($statusData);
        }
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

    private function saveKwitansi($request, $no_inv)
    {
        $getCustomer = M_Credit::with('customer')->where('LOAN_NUMBER', $request->no_facility)->first();

        if (!$getCustomer) {
            throw new Exception("Customer Not Found", 404);
        }

        $cekPaymentMethod = $request->payment_method == 'cash' && strtolower($request->bayar_dengan_diskon) != 'ya';
        $sttsPayment = $cekPaymentMethod && !$this->checkPosition($request) ? "PAID" : "PENDING";
        // $sttsPayment = $cekPaymentMethod ? "PAID" : "PENDING";

        $save_kwitansi = [
            "PAYMENT_TYPE" => 'angsuran',
            "PAYMENT_ID" => $request->uid,
            "STTS_PAYMENT" => $sttsPayment,
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
        $kwitansi = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if ($kwitansi) {
            $user_id = $kwitansi->CREATED_BY;
            $getCodeBranch = M_Branch::find($kwitansi->BRANCH_CODE);
        }

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => $res['flag'] == 'PAID' ? 'angsuran_denda' : $request->pembayaran ?? '',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv ?? '',
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? $request->payment_method,
            'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? $branch->CODE_NUMBER,
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
            'ORIGINAL_AMOUNT' => floatval($res['bayar_angsuran']) + floatval($res['bayar_denda']),
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran ?? null,
            'END_DATE' => now(),
            'USER_ID' => $user_id ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? '',
            'BANK_NAME' => round(microtime(true) * 1000)
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

    public function destroyImage(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $check = M_PaymentAttachment::findOrFail($id);

            $check->delete();

            DB::commit();
            return response()->json(['message' => 'deleted successfully', "status" => 200], 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
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
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $req);
        }
    }

    public function approval(Request $request)
    {
        DB::beginTransaction();
        try {
            $getNoInvoice = $request->no_invoice;
            $getFlag = $request->flag == 'yes' ? 'PAID' : 'REJECTED';
            $getCurrentPosition = strtolower($request->user()->position);

            $kwitansi = M_Kwitansi::where(['NO_TRANSAKSI' => $getNoInvoice, 'STTS_PAYMENT' => 'PENDING'])->lockForUpdate()->first();
            $getLoanNumber = $kwitansi->LOAN_NUMBER;

            if ($kwitansi) {
                $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

                $request->merge(['payment_method' => 'transfer']);

                if ($kwitansi->PAYMENT_TYPE === 'pelunasan') {
                    $type = "Pelunasan";
                } else {
                    $type = "Angsuran";
                }

                if ($request->flag == 'yes') {

                    if ($kwitansi->PAYMENT_TYPE === 'pelunasan') {
                        $this->pelunasan->proccess($request, $kwitansi->LOAN_NUMBER, $getNoInvoice, 'PAID');
                    } else {
                        $getKwitansiDetail = M_KwitansiStructurDetail::where([
                            'no_invoice' => $getNoInvoice
                        ])->orderBy('angsuran_ke', 'asc')->get();

                        if ($getKwitansiDetail->isEmpty()) {
                            throw new Exception("Kwitansi Detail Not Found", 404);
                        }

                        foreach ($getKwitansiDetail as $res) {
                            $request->merge(['approval' => 'approve', 'pembayaran' => $res['bayar_denda'] != 0 ? 'angsuran_denda' : 'angsuran', "diskon_flag" => $kwitansi->DISKON_FLAG]);
                            $this->processPaymentStructure($res, $request, $getCodeBranch, $getNoInvoice);
                        }
                    }

                    $kwitansi->update(['STTS_PAYMENT' => 'PAID']);

                    $this->taskslogging->create($request, $type . " Disetujui", 'payment', $getNoInvoice, $getFlag, $request->keterangan);
                } else {
                    if ($getCurrentPosition === 'admin') {
                        $setTitle = "Pembatalan Pembayaran";
                        $message = "A/n " . $kwitansi->NAMA . " Nominal : " . number_format($kwitansi->JUMLAH_UANG) . " Keterangan Cancel (" . $request->descr . ")";
                        $this->taskslogging->create($request, $setTitle, 'payment_cancel', $getNoInvoice, 'WAITING CANCEL', "Menunggu " . $setTitle . ' ' . $message);

                        $kwitansi->update(['STTS_PAYMENT' => 'WAITING CANCEL']);
                    } else {

                        $checkType = $kwitansi->PAYMENT_TYPE === 'pelunasan';

                        $detailModel = $checkType ? M_KwitansiDetailPelunasan::class : M_KwitansiStructurDetail::class;

                        $getKwitansiDetail = $detailModel::where([
                            'no_invoice'  => $getNoInvoice,
                            'loan_number' => $getLoanNumber
                        ])->orderBy('angsuran_ke', 'asc')->get();

                        if ($getKwitansiDetail->isEmpty()) {
                            throw new Exception("Kwitansi Detail Not Found", 404);
                        }

                        foreach ($getKwitansiDetail as $res) {
                            $loan_number   = $res['loan_number'];
                            $tgl_angsuran  = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');

                            $record = M_CreditSchedule::where([
                                'LOAN_NUMBER'  => $loan_number,
                                'PAYMENT_DATE' => $tgl_angsuran,
                                'PAID_FLAG'    => 'PENDING'
                            ])->first();

                            if ($record) {
                                $record->update(['PAID_FLAG' => '']);
                            }

                            $getInstallment  = floatval($res['installment']);
                            $getArrears = floatval($res['denda'] ?? 0);

                            $setArrearsCalculate = calculateArrears($getInstallment, $tgl_angsuran);

                            if ($checkType) {
                                $pastDuePenalty = $getInstallment != 0 ? $setArrearsCalculate : $getArrears;
                            } else {
                                $pastDuePenalty = $res['flag'] === 'PAID' ? $getArrears : $setArrearsCalculate;
                            }

                            M_Arrears::where([
                                'LOAN_NUMBER' => $loan_number,
                                'START_DATE'  => $tgl_angsuran,
                                'STATUS_REC'  => 'PENDING'
                            ])->update([
                                'STATUS_REC'       => 'A',
                                'PAST_DUE_PENALTY' => $pastDuePenalty,
                                'UPDATED_AT'       => Carbon::now()
                            ]);
                        }

                        $kwitansi->update(['STTS_PAYMENT' => $getFlag]);

                        $this->taskslogging->create($request, $type . " Ditolak", 'payment', $getNoInvoice, $getFlag, $request->keterangan);
                    }
                }
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
                'NO_TRANSAKSI' => $no_invoice
            ])
                ->whereIn('STTS_PAYMENT', ['PAID', 'WAITING CANCEL'])
                ->lockForUpdate()
                ->first();

            if (!$check) {
                throw new Exception("Kwitansi Number Not Exist", 404);
            }

            $check->update(['STTS_PAYMENT' => 'WAITING CANCEL']);

            if ($check->PAYMENT_TYPE === 'pelunasan') {
                $setTitle = "Pembatalan Pelunasan";
            } else {
                $setTitle = "Pembatalan Pembayaran";
            }

            $message = "A/n " . $check->NAMA . " Nominal : " . number_format($check->JUMLAH_UANG) . " Keterangan Cancel (" . $request->descr . ")";
            $this->taskslogging->create($request, $setTitle, 'payment_cancel', $no_invoice, 'WAITING CANCEL', "Menunggu " . $setTitle . ' ' . $message);

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
                    $status = "REJECTED";
                }

                $this->taskslogging->create($request, $title, $type, $no_invoice, $status, $title . " " . $message . " " . $request->keterangan ?? '');

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
        $getNoInvoice  = $request->no_invoice;
        $getLoanNumber = $check->LOAN_NUMBER;

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

                $getKwitansiDetail = M_KwitansiStructurDetail::where([
                    'no_invoice'   => $getNoInvoice,
                    'loan_number'  => $getLoanNumber
                ])
                    ->orderBy('angsuran_ke', 'asc')
                    ->get();

                if ($getKwitansiDetail->isEmpty()) {
                    throw new Exception("Kwitansi Detail Not Found", 404);
                }

                $ttlBayarPrincipal = 0;
                $ttlBayarInterest = 0;
                $ttlBayarDenda = 0;

                foreach ($getKwitansiDetail as $resList) {

                    $getInstallment = floatval($resList['installment'] ?? 0);
                    $getPrincipal = floatval($resList['principal'] ?? 0);
                    $getInterest = floatval($resList['interest'] ?? 0);
                    $getPrincipalPrev = floatval($resList['principal_prev'] ?? 0);
                    $getInterestPrev = floatval($resList['interest_prev'] ?? 0);
                    $getInsuficientPaymentPrev = floatval($resList['insuficient_payment_prev'] ?? 0);
                    $getPaymentValue = floatval($resList['payment'] ?? 0);
                    $getPaidFlag = $resList['flag'] ?? "";
                    $getBayarAngsuran = floatval($resList['bayar_angsuran']);
                    $getBayarDenda = floatval($resList['bayar_denda']);
                    $getTglAngsuran = Carbon::parse($resList['tgl_angsuran'])->format('Y-m-d') ?? null;
                    $getAngsuranKe = floatval($resList['angsuran_ke'] ?? 0);
                    $getArrears = floatval($resList['denda'] ?? 0);
                    $getDiskonArrears = floatval($resList['diskon_denda']);

                    $creditScheduleCheck = M_CreditSchedule::where([
                        'LOAN_NUMBER' => $getLoanNumber,
                        'PAYMENT_DATE' => $getTglAngsuran,
                        'INSTALLMENT_COUNT' => $getAngsuranKe,
                    ])->first();

                    if ($creditScheduleCheck) {
                        if ($getBayarAngsuran != 0) {
                            $creditScheduleCheck->update([
                                'PAYMENT_VALUE_PRINCIPAL' => $getPrincipalPrev,
                                'PAYMENT_VALUE_INTEREST' => $getInterestPrev,
                                'INSUFFICIENT_PAYMENT' => $getInsuficientPaymentPrev,
                                'PAYMENT_VALUE' => $getPaymentValue,
                                'PAID_FLAG' => $getPaidFlag
                            ]);

                            $ttlBayarPrincipal += $getPrincipalPrev != 0 ? $getPrincipalPrev : $getPrincipal;
                            $ttlBayarInterest += $getInterestPrev != 0 ? $getInterestPrev : $getInterest;
                        }
                    }

                    $arrearsCheck = M_Arrears::where([
                        'LOAN_NUMBER' => $getLoanNumber,
                        'START_DATE' => $getTglAngsuran
                    ])->first();

                    if ($arrearsCheck) {
                        $setArrearsCalculate = calculateArrears($getInstallment, $getTglAngsuran);

                        if ($getBayarDenda != 0 || $getDiskonArrears != 0 || $check->DISKON_FLAG == 'ya') {
                            $amountToSubtractFromPenalty = ($getBayarDenda != 0) ? $getBayarDenda : $getArrears;

                            $updateData = [
                                'PAID_PCPL' => $getPrincipalPrev,
                                'PAID_INT' =>  $getInterestPrev,
                                'PAID_PENALTY' => floatval($arrearsCheck->PAID_PENALTY) - $amountToSubtractFromPenalty,
                                'WOFF_PENALTY' => floatval($arrearsCheck->WOFF_PENALTY) - $getDiskonArrears,
                                'STATUS_REC' => 'A',
                            ];


                            if ($getPaidFlag != 'PAID') {
                                $updateData['PAST_DUE_PENALTY'] = $setArrearsCalculate;
                            }

                            $arrearsCheck->update($updateData);

                            $ttlBayarDenda += $getBayarDenda;
                        }
                    }
                }

                $creditCheck = M_Credit::where('LOAN_NUMBER', $getLoanNumber)
                    ->whereIn('STATUS', ['A', 'D'])
                    ->first();

                if ($creditCheck) {
                    $creditCheck->update([
                        'STATUS_REC' => 'AC',
                        'STATUS' => 'A',
                        'PAID_PRINCIPAL' => floatval($creditCheck->PAID_PRINCIPAL) - floatval($ttlBayarPrincipal),
                        'PAID_INTEREST' => floatval($creditCheck->PAID_INTEREST) - floatval($ttlBayarInterest),
                        'PAID_PENALTY' => floatval($creditCheck->PAID_PENALTY) - floatval($ttlBayarDenda),
                        'MOD_USER' => $request->user()->id,
                        'MOD_DATE' => Carbon::now(),
                    ]);
                }
            }
        } else {
            $check->update(['STTS_PAYMENT' => 'PAID']);
        }
    }

    public function processPaymentBungaMenurun(Request $request)
    {
        DB::beginTransaction();
        try {
            $loan_number = $request->LOAN_NUMBER;
            $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');

            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();
            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            $status = strtolower($request->METODE_PEMBAYARAN) === 'cash' ? "PAID" : 'PENDING';

            $this->saveKwitansiBungaMenurun($request, $detail_customer, $no_inv, $status);
            $this->proccessKwitansiDetail($request, $loan_number, $no_inv);
            $this->processPokokBungaMenurun($request,$loan_number, $no_inv);

            $data = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

            $dto = new R_Kwitansi($data);

            DB::commit();
            return response()->json($dto, 200);
        } catch (Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    private function saveKwitansiBungaMenurun($request, $customer, $no_inv, $status)
    {
        $checkKwitansiExist = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if ($checkKwitansiExist) {
            throw new Exception("Kwitansi Exist", 500);
        }

        $idGenerate = Uuid::uuid7()->toString();

        $data = [
            "PAYMENT_TYPE" => 'pokok_sebagian',
            "PAYMENT_ID" => $idGenerate ?? '',
            "STTS_PAYMENT" => $status,
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->LOAN_NUMBER,
            "TGL_TRANSAKSI" => Carbon::now(),
            "CUST_CODE" => $customer->CUST_CODE,
            "BRANCH_CODE" => $request->user()->branch_id,
            "NAMA" => $customer->NAME,
            "ALAMAT" => $customer->ADDRESS,
            "RT" => $customer->RT,
            "RW" => $customer->RW,
            "PROVINSI" => $customer->PROVINCE,
            "KOTA" => $customer->CITY,
            "KECAMATAN" => $customer->KECAMATAN,
            "KELURAHAN" => $customer->KELURAHAN,
            "METODE_PEMBAYARAN" => $request->METODE_PEMBAYARAN,
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR ?? 0,
            "PINALTY_PELUNASAN" => 0,
            "DISKON_PINALTY_PELUNASAN" => 0,
            "PEMBULATAN" => $request->PEMBULATAN ?? 0,
            "DISKON" => $request->JUMLAH_DISKON ?? 0,
            "KEMBALIAN" => $request->KEMBALIAN ?? 0,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->id
        ];

        M_Kwitansi::create($data);
    }

    private function proccessKwitansiDetail($request, $loan_number, $no_inv)
    {
        $creditSchedules = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '!=', 'PAID')
                    ->orWhere('PAID_FLAG', '=', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->orderBy('INSTALLMENT_COUNT', 'ASC')
            ->first();

        if ($creditSchedules) {
            M_KwitansiStructurDetail::firstOrCreate([
                'no_invoice' => $no_inv,
                'loan_number' => $creditSchedules['LOAN_NUMBER'] ?? ''
            ], [
                'key' => $creditSchedules['INSTALLMENT_COUNT'] ?? 1,
                'angsuran_ke' => $creditSchedules['INSTALLMENT_COUNT'] ?? '',
                'tgl_angsuran' => Carbon::parse($creditSchedules['PAYMENT_DATE'])->format('d-m-Y') ?? null,
                'principal' => $creditSchedules['PRINCIPAL'] ?? '',
                'interest' => $creditSchedules['INTEREST'] ?? '',
                'installment' => $creditSchedules['INSTALLMENT'] ?? '',
                'principal_remains' => $creditSchedules['PRINCIPAL_REMAINS'] ?? '',
                'principal_prev' => $creditSchedules['principal_prev'] ?? 0,
                'payment' => $creditSchedules['INSTALLMENT'] ?? '',
                'bayar_angsuran' => $request->UANG_PELANGGAN ?? '0',
                'bayar_denda' => '0',
                'total_bayar' => $request->TOTAL_BAYAR ?? '',
                'flag' => $creditSchedules['PAID_FLAG'] ?? '',
                'denda' => '0',
                'diskon_denda' => 0
            ]);
        }
    }

    private function processPokokBungaMenurun($request,$loan_number, $no_inv)
    {
        $kwitansi = M_KwitansiStructurDetail::where([
            'loan_number' => $loan_number,
            'no_invoice' => $no_inv
        ])->first();

        if (!$kwitansi) return;

        $byr_angsuran = floatval($kwitansi->bayar_angsuran);

        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'INSTALLMENT_COUNT' => floatval($kwitansi->angsuran_ke ?? 0)
        ])->first();

        if (!$credit_schedule) return;

        $getInterest = floatval($credit_schedule->INTEREST);
        $getPrincipalPay = floatval($byr_angsuran);

        $kwitansi = M_Kwitansi::where('NO_TRANSAKSI', $no_inv)->first();

        if ($kwitansi) {
            $user_id = $kwitansi->CREATED_BY;
            $getCodeBranch = M_Branch::find($kwitansi->BRANCH_CODE);
        }

        $uid= Uuid::uuid7()->toString();

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => 'pokok_sebagian',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv ?? '',
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? $request->payment_method,
            'BRANCH' =>  $getCodeBranch->CODE_NUMBER ?? '',
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Pembayaran Pokok Sebagian',
            'ORIGINAL_AMOUNT' => $getPrincipalPay,
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran ?? null,
            'END_DATE' => now(),
            'USER_ID' => $user_id ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? '',
            'BANK_NAME' => round(microtime(true) * 1000)
        ];

        $existing = M_Payment::where($paymentData)->first();

        if (!$existing) {
            $paymentData['BANK_NAME'] = round(microtime(true) * 1000);
            M_Payment::create($paymentData);
        }

        $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $getPrincipalPay);
        M_PaymentDetail::create($data);

        $credit_schedule->update([
            'PAYMENT_VALUE_PRINCIPAL' => ($credit_schedule->PAYMENT_VALUE_PRINCIPAL + $getPrincipalPay),
            'INSUFFICIENT_PAYMENT' => $getInterest,
            'PAYMENT_VALUE' => $getPrincipalPay
        ]);

        $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $getPrincipalPay]);

        // Ambil semua angsuran belum dibayar
        $creditSchedulesUpdate = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '!=', 'PAID')
                    ->orWhere('PAID_FLAG', '=', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->where(function ($query) {
                $query->WhereNull('PAYMENT_VALUE_PRINCIPAL')
                    ->orWhere('PAYMENT_VALUE_PRINCIPAL', '=', 0);
            })
            ->orderBy('INSTALLMENT_COUNT', 'ASC')
            ->orderBy('PAYMENT_DATE', 'ASC')
            ->get();

        if ($creditSchedulesUpdate->isEmpty()) return;

        // Hitung total pokok yang belum dibayar
        $totalSisaPokok = $creditSchedulesUpdate->sum('PRINCIPAL');

        // // Kurangi dengan pokok yang baru saja dibayar
        $sisa_pokok = $totalSisaPokok - $getPrincipalPay;
        $sisa_pokok = max(0, $sisa_pokok);

        // // Buat objek data dasar amortisasi
        $getNewTenor = count($creditSchedulesUpdate);
        $calc = round($sisa_pokok * (3 / 100), 2);

        $data = new \stdClass();
        $data->SUBMISSION_VALUE = $sisa_pokok;
        $data->TOTAL_ADMIN = 0;
        $data->INSTALLMENT = $calc;
        $data->TENOR = $getNewTenor;
        $data->START_FROM = $creditSchedulesUpdate->first()->INSTALLMENT_COUNT;

        $data_credit_schedule = $this->generateAmortizationScheduleBungaMenurun($data);

        foreach ($creditSchedulesUpdate as $index => $schedule) {
            $updateData = $data_credit_schedule[$index];

            $schedule->update([
                'PRINCIPAL' => $updateData['pokok'],
                'INTEREST' => $updateData['bunga'],
                'INSTALLMENT' => $updateData['total_angsuran'],
                'PRINCIPAL_REMAINS' => $updateData['baki_debet'],
            ]);
        }
    }

    private function generateAmortizationScheduleBungaMenurun($data)
    {
        $schedule = [];
        $ttal_bayar = ($data->SUBMISSION_VALUE + $data->TOTAL_ADMIN);
        $angsuran_bunga = $data->INSTALLMENT;
        $term = ceil($data->TENOR);
        $baki_debet = $ttal_bayar;

        $startInstallment = $data->START_FROM ?? 1;

        for ($i = 0; $i < $term; $i++) {
            $pokok = 0;

            if ($i == $term - 1) {
                $pokok = $ttal_bayar;
            }

            $total_angsuran = $pokok + $angsuran_bunga;

            $schedule[] = [
                'angsuran_ke' => $startInstallment + $i,
                'baki_debet_awal' => floatval($baki_debet),
                'pokok' => floatval($pokok),
                'bunga' => floatval($angsuran_bunga),
                'total_angsuran' => floatval($total_angsuran),
                'baki_debet' => floatval($baki_debet - $pokok)
            ];

            $baki_debet -= $pokok;
        }

        return $schedule;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Pelunasan;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\M_Payment;
use App\Models\M_PaymentDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PelunasanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_Kwitansi::where('PAYMENT_TYPE','pelunasan')->get();

            $dto = R_Pelunasan::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function checkCredit(Request $request)
    {
        try {

            $loan_number = $request->loan_number;

            $result = DB::select(
                "select	(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as SISA_POKOK,
                        c.BUNGA as BUNGA_BERJALAN,
                        b.INT_ARR as TUNGGAKAN_BUNGA,
                        b.TUNGGAKAN_DENDA as TUNGGAKAN_DENDA,
                        b.DENDA_TOTAL as DENDA,
                        (coalesce(a.PENALTY_RATE,3)/100)*(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as PINALTI,
                        d.DISC_BUNGA
                from	credit a
                        left join (	select	LOAN_NUMBER, 
                                            sum(coalesce(PAST_DUE_INTRST,0))-sum(coalesce(PAID_INT,0)) as INT_ARR, 
                                            sum(case when STATUS_REC <> 'A' then coalesce(PAST_DUE_PENALTY,0) end)-
                                                sum(case when STATUS_REC <> 'A' then coalesce(PAID_PENALTY,0) end) as TUNGGAKAN_DENDA,
                                            sum(coalesce(PAST_DUE_PENALTY,0))-sum(coalesce(PAID_PENALTY,0)) as DENDA_TOTAL
                                    from	arrears
                                    where	LOAN_NUMBER = '{$loan_number}'
                                    group 	by LOAN_NUMBER) b
                            on b.LOAN_NUMBER = a.LOAN_NUMBER
                        left join (	select	LOAN_NUMBER, 
                                            INTEREST * datediff(now(), PAYMENT_DATE) / 
                                                date_format(date_add(date_add(str_to_date(concat('01',date_format(PAYMENT_DATE,'%m%Y')),'%d%m%Y'),interval 1 month),interval -1 day),'%m') as BUNGA
                                    from	credit_schedule
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and PAYMENT_DATE = (	select	max(PAYMENT_DATE)
                                                                    from	credit_schedule
                                                                    where	LOAN_NUMBER = '{$loan_number}'
                                                                            and PAYMENT_DATE <= now())) c
                            on c.LOAN_NUMBER = a.LOAN_NUMBER
                        left join (	select	LOAN_NUMBER, INTEREST-PAYMENT_VALUE_INTEREST as DISC_BUNGA
                                    from	credit_schedule
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and PAYMENT_DATE = (	select	max(PAYMENT_DATE)
                                                                    from	credit_schedule
                                                                    where	LOAN_NUMBER = '{$loan_number}'
                                                                            and PAYMENT_DATE>now())) d
                            on d.LOAN_NUMBER = a.LOAN_NUMBER
                where a.LOAN_NUMBER = '{$loan_number}'"
            );

            $processedResults = array_map(function ($item) {
                return [
                    'SISA_POKOK' => round(floatval($item->SISA_POKOK), 2),
                    'BUNGA_BERJALAN' => round(floatval($item->BUNGA_BERJALAN), 2),
                    'TUNGGAKAN_BUNGA' => round(floatval($item->TUNGGAKAN_BUNGA), 2),
                    'TUNGGAKAN_DENDA' => round(floatval($item->TUNGGAKAN_DENDA), 2),
                    'DENDA' => round(floatval($item->DENDA), 2),
                    'PINALTI' => round(floatval($item->PINALTI), 2),
                ];
            }, $result);

            return response()->json($processedResults, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function processPayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $loan_number = $request->LOAN_NUMBER;
            $uid = Uuid::uuid7()->toString();
            $no_inv = generateCode($request, 'payment', 'INVOICE', 'INV');
    
            M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->firstOrFail();
            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();
            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();
    
            $this->updateCredit($credit, $request);
            $this->saveReceipt($request, $detail_customer, $no_inv);
            
            $status = $this->determineStatus($request);
            $creditSchedule = $this->getCreditSchedule($loan_number);
            $arrears = $this->getArrears($loan_number);
    
            $payment_record = $this->preparePaymentRecord($request, $uid, $no_inv, $status, $creditSchedule);
            M_Payment::create($payment_record);
    
            $this->handlePaymentsAndDiscounts($uid, $request);
    
            $this->processInstallments($creditSchedule, $request);
            $this->processArrears($arrears, $request);
    
            $response = $this->prepareResponse($no_inv, $detail_customer, $request);
            DB::commit();
    
            return response()->json($response, 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function processArrears($arrears, $request)
    {
        $bayarPokok = $request->input('BAYAR_POKOK');
        $bayarBunga = $request->input('BAYAR_BUNGA');
        $bayarPinalty = $request->input('BAYAR_PINALTI');
    
        $tolerance = 0.3;
        foreach ($arrears as $res) {
            $payment_value_principal = min($bayarPokok, $res['PAST_DUE_PCPL']);
            $bayarPokok -= $payment_value_principal;
    
            $payment_value_interest = min($bayarBunga, $res['PAST_DUE_INTRST']);
            $bayarBunga -= $payment_value_interest;

            $payment_penalty = min($bayarPinalty, $res['PAST_DUE_PENALTY']);
            $bayarPinalty -= $payment_penalty;
    
            $res->update([
                'PAID_PCPL' => $payment_value_principal,
                'PAID_INT' => $payment_value_interest,
                'PAID_PENALTY' => $payment_penalty,
                'STATUS_REC' => (abs($payment_value_principal - $res['PAST_DUE_PCPL']) <= $tolerance) && (abs($payment_value_interest - $res['PAST_DUE_INTRST']) <= $tolerance) ? 'D' : 'A'
            ]);
    
            if ($bayarPokok <= 0 && $bayarBunga <= 0) {
                break;
            }
        }
    }
    
    private function updateCredit($credit, $request)
    {
        $credit->update([
            'PAID_PRINCIPAL' => $request->BAYAR_POKOK,
            'PAID_INTEREST' => $request->BAYAR_BUNGA,
            'PAID_PINALTY' => $request->BAYAR_PINALTI,
            'STATUS' => $credit->PCPL_ORI == $request->BAYAR_POKOK ? 'D' : 'A'
        ]);
    }
    
    private function saveReceipt($request, $customer, $no_inv)
    {
        $data = [
            "PAYMENT_TYPE" => 'pelunasan',
            "STTS_PAYMENT" => $request->METODE_PEMBAYARAN == 'cash' ? "PAID" : "PENDING",
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->LOAN_NUMBER,
            "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
            'CUST_CODE' => $customer->CUST_CODE,
            'BRANCH_CODE' => $request->user()->branch_id,
            'NAMA' => $customer->NAME,
            'ALAMAT' => $customer->ADDRESS,
            'RT' => $customer->RT,
            'RW' => $customer->RW,
            'PROVINSI' => $customer->PROVINCE,
            'KOTA' => $customer->CITY,
            'KELUMATAN' => $customer->KECAMATAN,
            "METODRAHAN' => $customer->KELURAHAN,
            'KECAE_PEMBAYARAN" => $request->METODE_PEMBAYARAN,
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR,
            "PEMBULATAN" => $request->PEMBULATAN,
            "KEMBALIAN" => $request->KEMBALIAN,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->fullname
        ];
    
        M_Kwitansi::create($data);
    }
    
    private function determineStatus($request)
    {
        $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

        if (array_sum($discounts) > 0){
            $val = "PENDING";
        }elseif (strtolower($request->METODE_PEMBAYARAN) === 'transfer') {
            $val = "PENDING";
        }else{
            $val = "PAID";
        }

        return $val ;
    }
    
    private function getCreditSchedule($loan_number)
    {
        return M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function($query) {
                $query->where('PAID_FLAG', '!=', 'PAID')->orWhereNull('PAID_FLAG');
            })->get();
    }

    private function getArrears($loan_number)
    {
        return M_Arrears::where(['LOAN_NUMBER' => $loan_number,'STATUS_REC' => 'A'])->get();
    }
    
    private function preparePaymentRecord($request, $uid, $no_inv, $status, $creditSchedule)
    {
        $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);
        $installmentCounts = $creditSchedule->pluck('INSTALLMENT_COUNT')->join(',');
    
        return [
            'ID' => $uid,
            'ACC_KEY' => 'pelunasan',
            'STTS_RCRD' => $status,
            'INVOICE' => $no_inv,
            'NO_TRX' => $request->uid,
            'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN,
            'BRANCH' => $getCodeBranch->CODE_NUMBER,
            'LOAN_NUM' => $request->LOAN_NUMBER,
            'ENTRY_DATE' => Carbon::now(),
            'TITLE' => 'Angsuran Ke-' . $installmentCounts,
            'ORIGINAL_AMOUNT' => $request->TOTAL_BAYAR,
            'OS_AMOUNT' => 0,
            'AUTH_BY' => $request->user()->id,
            'AUTH_DATE' => Carbon::now()
        ];
    }
    
    private function handlePaymentsAndDiscounts($uid, $request)
    {
        $payments = [
            'BAYAR_POKOK' => 'PELUNASAN POKOK',
            'BAYAR_BUNGA' => 'BAYAR PELUNASAN BUNGA',
            'BAYAR_PINALTI' => 'BAYAR PELUNASAN PINALTY',
            'BAYAR_DENDA' => 'BAYAR PELUNASAN DENDA'
        ];
    
        foreach ($payments as $key => $description) {
            if ($request->$key != 0) {
                $data = $this->preparePaymentData($uid, $description, $request->$key);
                M_PaymentDetail::create($data);
            }
        }
    
        $discounts = [
            'DISKON_POKOK' => 'DISKON POKOK',
            'DISKON_BUNGA' => 'DISKON BUNGA',
            'DISKON_PINALTI' => 'DISKON PINALTY',
            'DISKON_DENDA' => 'DISKON DENDA'
        ];
    
        foreach ($discounts as $key => $description) {
            if ($request->$key != 0) {
                $data = $this->preparePaymentData($uid, $description, $request->$key);
                M_PaymentDetail::create($data);
            }
        }
    }

    function preparePaymentData($payment_id,$acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }
    
    private function processInstallments($creditSchedule, $request)
    {
        $bayarPokok = $request->input('BAYAR_POKOK');
        $bayarBunga = $request->input('BAYAR_BUNGA');
    
        $tolerance = 0.3;
        foreach ($creditSchedule as $res) {
            $payment_value_principal = min($bayarPokok, $res['PRINCIPAL']);
            $bayarPokok -= $payment_value_principal;
    
            $payment_value_interest = min($bayarBunga, $res['INTEREST']);
            $bayarBunga -= $payment_value_interest;
    
            $res->update([
                'PAYMENT_VALUE_PRINCIPAL' => $payment_value_principal,
                'PAYMENT_VALUE_INTEREST' => $payment_value_interest,
                'PAYMENT_VALUE' => $payment_value_principal + $payment_value_interest,
                'PAID_FLAG' => abs($payment_value_principal - $res['PRINCIPAL']) <= $tolerance ? 'PAID' : ''
            ]);
    
            if ($bayarPokok <= 0 && $bayarBunga <= 0) {
                break;
            }
        }
    }
    
    private function prepareResponse($no_inv, $customer, $request)
    {
        return [
            "no_transaksi" => $no_inv,
            'cust_code' => $customer->CUST_CODE,
            'nama' => $customer->NAME,
            'alamat' => $customer->ADDRESS,
            'rt' => $customer->RT,
            'rw' => $customer->RW,
            'provinsi' => $customer->PROVINCE,
            'kota' => $customer->CITY,
            'kelurahan' => $customer->KELURAHAN,
            'kecamatan' => $customer->KECAMATAN,
            "tgl_transaksi" => Carbon::now()->format('d-m-Y'),
            "payment_method" => $request->METODE_PEMBAYARAN,
            "nama_bank" => $request->NAMA_BANK,
            "no_rekening" => $request->NO_REKENING,
            "bukti_transfer" => '',
            "pembayaran" => 'PELUNASAN',
            "pembulatan" => $request->PEMBULATAN,
            "kembalian" => $request->KEMBALIAN,
            "jumlah_uang" => $request->UANG_PELANGGAN,
            "terbilang" => bilangan($request->TOTAL_BAYAR) ?? null,
            "created_by" => $request->user()->fullname,
            "created_at" => Carbon::parse(Carbon::now())->format('d-m-Y')
        ];
    }
    
}



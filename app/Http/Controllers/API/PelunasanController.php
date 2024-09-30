<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Pelunasan;
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
                        b.DENDA as DENDA,
                        (coalesce(a.PENALTY_RATE,3)/100)*(a.PCPL_ORI-coalesce(a.PAID_PRINCIPAL,0)) as PINALTI
                from	credit a
                        left join (	select	LOAN_NUMBER, 
                                            sum(PAST_DUE_INTRST)-sum(PAID_INT) as INT_ARR, 
                                            sum(PAST_DUE_PENALTY)-sum(PAID_PENALTY) as DENDA
                                    from	arrears
                                    where	LOAN_NUMBER = '{$loan_number}'
                                            and STATUS_REC = 'A'
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
                where a.LOAN_NUMBER = '{$loan_number}'"
            );

            $processedResults = array_map(function ($item) {
                    return [
                        'SISA_POKOK' => intval($item->SISA_POKOK),
                        'BUNGA_BERJALAN' => intval($item->BUNGA_BERJALAN),
                        'TUNGGAKAN_BUNGA' => intval($item->TUNGGAKAN_BUNGA),
                        'DENDA' => intval($item->DENDA),
                        'PINALTI' => intval($item->PINALTI),
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
            DB::commit();

            $loan_number = $request->LOAN_NUMBER;
            $uid = Uuid::uuid7()->toString();
            $created_now = Carbon::now();
            $no_inv = generateCode($request, 'payment', 'INVOICE', 'INV');
            $getCodeBranch = M_Branch::findOrFail($request->user()->branch_id);

            $check = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)->first();

            if (!$check) {
                throw new Exception('Loan Number Not Exist');
            }

            $credit = M_Credit::where('LOAN_NUMBER', $loan_number)->firstOrFail();

            if ($credit) {
                $credit->update([
                    'PAID_PRINCIPAL' => $request->BAYAR_POKOK,
                    'PAID_INTEREST' => $request->BAYAR_BUNGA,
                    'PAID_PINALTY' => $request->BAYAR_PINALTI
                ]);
            }

            $detail_customer = M_Customer::where('CUST_CODE', $credit->CUST_CODE)->firstOrFail();

            $save_kwitansi = [
                "PAYMENT_TYPE" => 'pelunasan',
                "NO_TRANSAKSI" => $no_inv,
                "LOAN_NUMBER" => $request->LOAN_NUMBER ?? null,
                "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
                'CUST_CODE' => $detail_customer->CUST_CODE,
                'NAMA' => $detail_customer->NAME,
                'ALAMAT' => $detail_customer->ADDRESS,
                'RT' => $detail_customer->RT,
                'RW' => $detail_customer->RW,
                'PROVINSI' => $detail_customer->PROVINCE,
                'KOTA' => $detail_customer->CITY,
                'KELURAHAN' => $detail_customer->KELURAHAN,
                'KECAMATAN' => $detail_customer->KECAMATAN,
                "METODE_PEMBAYARAN" => $request->METODE_PEMBAYARAN ?? null,
                "TOTAL_BAYAR" => $request->TOTAL_BAYAR ?? null,
                "PEMBULATAN" => $request->PEMBULATAN ?? null,
                "KEMBALIAN" => $request->KEMBALIAN ?? null,
                "JUMLAH_UANG" => $request->UANG_PELANGGAN ?? null,
                "NAMA_BANK" => $request->NAMA_BANK ?? null,
                "NO_REKENING" => $request->NO_REKENING ?? null,
                "CREATED_BY" => $request->user()->fullname
            ];

            M_Kwitansi::create($save_kwitansi);

            $discounts = $request->only(['DISKON_POKOK', 'DISKON_PINALTI', 'DISKON_BUNGA', 'DISKON_DENDA']);

            // Check if all discount values are non-zero
            $allDiscountsPaid = collect($discounts)->every(fn ($value) => $value != 0);

            // Check if the payment method is cash
            $checkMethodPayment = strtolower($request->METODE_PEMBAYARAN) === 'cash';

            // Determine the status
            $status = $allDiscountsPaid ? 'PAID' : 'PENDING';

            // If payment method is cash, set status to PENDING regardless of discounts
            if ($checkMethodPayment) {
                $status = 'PENDING';
            }

            $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $request->LOAN_NUMBER)
                ->whereNull('PAID_FLAG')
                ->get();

            $installmentCounts = $creditSchedule->map(function ($item) {
                return $item->INSTALLMENT_COUNT;
            })->join(',');

            $payment_record = [
                'ID' => $uid,
                'ACC_KEY' => 'pelunasan',
                'STTS_RCRD' => $status,
                'INVOICE' => $no_inv,
                'NO_TRX' => $request->uid ?? null,
                'PAYMENT_METHOD' => $request->METODE_PEMBAYARAN,
                'BRANCH' => $getCodeBranch->CODE_NUMBER,
                'LOAN_NUM' => $request->LOAN_NUMBER ?? null,
                'VALUE_DATE' => null,
                'ENTRY_DATE' => $created_now,
                'TITLE' => 'Angsuran Ke-' . $installmentCounts,
                'ORIGINAL_AMOUNT' => $request->TOTAL_BAYAR,
                'OS_AMOUNT' => 0,
                'START_DATE' => null,
                'AUTH_BY' => $request->user()->id,
                'AUTH_DATE' => $created_now
            ];

            M_Payment::create($payment_record);

            $payments = [
                'BAYAR_POKOK'   => 'PELUNASAN POKOK',
                'BAYAR_BUNGA'   => 'BAYAR PELUNASAN BUNGA',
                'BAYAR_PINALTI' => 'BAYAR PELUNASAN PINALTY',
                'BAYAR_DENDA'   => 'BAYAR PELUNASAN DENDA'
            ];

            $discounts = [
                'DISKON_POKOK'   => 'DISKON POKOK',
                'DISKON_BUNGA'   => 'DISKON BUNGA',
                'DISKON_PINALTI' => 'DISKON PINALTY',
                'DISKON_DENDA'   => 'DISKON DENDA'
            ];

            // Handle payments
            foreach ($payments as $key => $description) {
                if ($request->$key != 0) {
                    $data = self::preparePaymentData($uid, $description, $request->$key);
                    M_PaymentDetail::create($data);
                }
            }

            // Handle discounts
            foreach ($discounts as $key => $description) {
                if ($request->$key != 0) {
                    $data = self::preparePaymentData($uid, $description, $request->$key);
                    M_PaymentDetail::create($data);
                }
            }

            $bayarPokok = $request->input('BAYAR_POKOK');
            $bayarBunga = $request->input('BAYAR_BUNGA');

            foreach ($creditSchedule as $res) {
                if ($bayarPokok > 0) {
                    if ($bayarPokok >= $res['PRINCIPAL']) {
                        $payment_value_principal = $res['PRINCIPAL'];
                        $bayarPokok -= $res['PRINCIPAL'];
                    } else {
                        $payment_value_principal = $bayarPokok;
                        $bayarPokok = 0;
                    }
                } else {
                    $payment_value_principal = 0;
                }

                // Calculate for interest
                if ($bayarBunga > 0) {
                    if ($bayarBunga >= $res['INTEREST']) {
                        $payment_value_interest = $res['INTEREST'];
                        $bayarBunga -= $res['INTEREST'];
                    } else {
                        $payment_value_interest = $bayarBunga;
                        $bayarBunga = 0;
                    }
                } else {
                    $payment_value_interest = 0;
                }

                // Total payment value (principal + interest)
                $payment_value = $payment_value_principal + $payment_value_interest;

                // Check if the installment is fully paid
                $isPaid = $payment_value == $res['PRINCIPAL'] ? 'PAID' : '';

                // Update the credit schedule record
                $res->update([
                    'PAYMENT_VALUE_PRINCIPAL' => $payment_value_principal,
                    'PAYMENT_VALUE_INTEREST' => $payment_value_interest,
                    'PAYMENT_VALUE' => $payment_value,
                    'PAID_FLAG' => $isPaid
                ]);

                // Break the loop if both `BAYAR_POKOK` and `BAYAR_BUNGA` are fully used
                if ($bayarPokok <= 0 && $bayarBunga <= 0) {
                    break;
                }
            }

            $response = [
                "no_transaksi" => $no_inv,
                'cust_code' => $detail_customer->CUST_CODE,
                'nama' => $detail_customer->NAME,
                'alamat' => $detail_customer->ADDRESS,
                'rt' => $detail_customer->RT,
                'rw' => $detail_customer->RW,
                'provinsi' => $detail_customer->PROVINCE,
                'kota' => $detail_customer->CITY,
                'kelurahan' => $detail_customer->KELURAHAN,
                'kecamatan' => $detail_customer->KECAMATAN,
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
                "created_at" => Carbon::parse($created_now)->format('d-m-Y')
            ];

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

    function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount,
            'OS_AMOUNT' => 0
        ];
    }
}



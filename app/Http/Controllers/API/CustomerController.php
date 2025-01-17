<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CreditList;
use App\Http\Resources\R_CustomerSearch;
use App\Models\M_Arrears;
use App\Models\M_CrCollateral;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_CrSurveyDocument;
use App\Models\M_Customer;
use App\Models\M_CustomerDocument;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        try {

            $search = $request->get('search');
            
            if(isset($search)){
                $customers = M_Customer::where('CUST_CODE', 'LIKE', "%{$search}%")
                                        ->orWhere(DB::raw("CONCAT(NAME, ' ', ALIAS)"), 'LIKE', "%{$search}%") 
                                        ->orWhere('MOTHER_NAME', 'LIKE', "%{$search}%")
                                        ->paginate(10);
            }else{
                $customers = M_Customer::paginate(10);
            }

            $customers->getCollection()->transform(function ($customer) {
                $credit = M_Credit::where('CUST_CODE', $customer->CUST_CODE)->first();
        
                if (!empty($credit->ID)) {
                    $customer->jaminan = M_CrCollateral::where('CR_CREDIT_ID', $credit->ID)->first();
                }
        
                return $customer;
            });

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($customers, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function searchCustomer(Request $request)
    {
        try {

            if (empty($request->nama) && empty($request->no_kontrak) && empty($request->no_polisi)) {
                return collect([]);
            }
        
            // Base query with eager loading
            $query = DB::table('credit as a')
                ->select([
                    'a.STATUS',
                    'a.LOAN_NUMBER',
                    'a.ORDER_NUMBER',
                    'c.NAME',
                    'c.ALIAS',
                    'c.ADDRESS',
                    'b.POLICE_NUMBER',
                    'a.INSTALLMENT'
                ])
                ->leftJoin('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                ->leftJoin('customer as c', 'c.CUST_CODE', '=', 'a.CUST_CODE')
                ->where('a.STATUS', '!=', 'D');
        
            if(!empty($request->nama)){
                $query->when($request->nama, function ($query, $nama) {
                    return $query->where(DB::raw("CONCAT(c.NAME, ' ', c.ALIAS)"), 'LIKE', "%{$nama}%");
                });
            }

            if(!empty($request->no_kontrak)){
                $query->when($request->no_kontrak, function ($query, $no_kontrak) {
                    return $query->where('a.LOAN_NUMBER', 'LIKE', "%{$no_kontrak}%");
                });    
            }

            if(!empty($request->no_polisi)){
                $query->when($request->no_polisi, function ($query, $no_polisi) {
                    return $query->where('b.POLICE_NUMBER', 'LIKE', "%{$no_polisi}%");
                });
            }
        
            $results = $query->get();

            $dto = R_CustomerSearch::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function fasilitas(Request $request)
    {
        try {
            $data =  M_Credit::where(['CUST_CODE' => $request->cust_code,'STATUS' =>'A'])->get();

            if ($data->isEmpty()) {
                throw new Exception("Cust Code Is Not Exist");
            }

            $dto = R_CreditList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function creditStruktur(Request $request)
    {
        try {
            $schedule = [];

            // $data = M_CreditSchedule::where('LOAN_NUMBER', $request->loan_number)
            // ->where(function ($query) {
            //     $query->whereNull('PAID_FLAG')
            //         ->orWhere('PAID_FLAG', '<>', 'PAID');
            // })
            // ->get();

            $loanNumber = $request->loan_number;

            $data = DB::table('credit_schedule as a')
                        ->leftJoin('arrears as b', function($join) {
                            $join->on('b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                                ->whereRaw('b.START_DATE = a.PAYMENT_DATE');
                        })
                        ->where('a.LOAN_NUMBER', $request->loan_number)
                        ->where('b.STATUS_REC', '=', 'A')
                        ->select('a.*', 'b.PAST_DUE_PENALTY', 'b.PAID_PENALTY')
                        ->orderBy("a.INSTALLMENT_COUNT","ASC")
                        ->get();

            if ($data->isEmpty()) {
                throw new Exception("Loan Number Is Not Exist");
            }

            $j = 0;
            foreach ($data as $res) {
                // $arrears = M_Arrears::where(['LOAN_NUMBER' => $res->LOAN_NUMBER,'START_DATE' => $res->PAYMENT_DATE])->first();

                $schedule[]=[
                    'key' => $j++,
                    'angsuran_ke' =>  $res->INSTALLMENT_COUNT,
                    'loan_number' => $res->LOAN_NUMBER,
                    'tgl_angsuran' => Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y'),
                    'principal' => floatval($res->PRINCIPAL),
                    'interest' => floatval($res->INTEREST),
                    'installment' => floatval($res->INSTALLMENT) - floatval($res->PAYMENT_VALUE),
                    'principal_remains' => floatval($res->PRINCIPAL_REMAINS),
                    'payment' => floatval($res->PAYMENT_VALUE),
                    'bayar_angsuran' => floatval($res->INSTALLMENT) - floatval($res->PAYMENT_VALUE),
                    'bayar_denda' => floatval($res->PAST_DUE_PENALTY ?? 0) - floatval($res->PAID_PENALTY ?? 0),
                    'total_bayar' => floatval($res->INSTALLMENT+($res->PAST_DUE_PENALTY??0)),
                    'flag' => $res->PAID_FLAG,
                    'denda' => floatval($res->PAST_DUE_PENALTY ?? 0) - floatval($res->PAID_PENALTY ?? 0) 
                ];
            }

            // if (isset($request->jumlah_uang)) {
            //     $data = M_CreditSchedule::where('loan_number',$request->loan_number)->get();
            //     // $paymentAmount = $request->jumlah_uang;

            //     // $j = 0;
            //     // foreach ($data as $scheduleItem) {
            //     //     $initialPaymentValue = $scheduleItem->PAYMENT_VALUE;
            //     //     $arrears = M_Arrears::where(['LOAN_NUMBER' => $scheduleItem->LOAN_NUMBER, 'START_DATE' => $scheduleItem->PAYMENT_DATE])->first();
                
            //     //     if ($paymentAmount > 0) {
            //     //         $installment = $scheduleItem->INSTALLMENT;
            //     //         $remainingPayment = $installment - $scheduleItem->PAYMENT_VALUE;
                
            //     //         // Pay the installment first
            //     //         if ($remainingPayment > 0) {
            //     //             $paymentValue = min($paymentAmount, $remainingPayment);
            //     //             $scheduleItem->PAYMENT_VALUE += $paymentValue;
            //     //             $paymentAmount -= $paymentValue;
            //     //         }
                
            //     //         // After paying the installment, check if there's enough to pay the penalty
            //     //         $penaltyPaid = 0;
            //     //         if ($scheduleItem->PAYMENT_VALUE == $installment && $arrears && $paymentAmount > 0) {
            //     //             $penalty = $arrears->PAST_DUE_PENALTY ?? 0;
                
            //     //             if ($paymentAmount >= $penalty) {
            //     //                 $penaltyPaid = $penalty;
            //     //                 $scheduleItem->PAYMENT_VALUE += $penalty;
            //     //                 $paymentAmount -= $penalty;
            //     //             } else {
            //     //                 // If there's not enough to cover the full penalty, pay only what's remaining
            //     //                 $penaltyPaid = $paymentAmount;
            //     //                 $scheduleItem->PAYMENT_VALUE += $paymentAmount;
            //     //                 $paymentAmount = 0;
            //     //             }
            //     //         }
                
            //     //         // Mark as paid if both installment and penalty (if applicable) are fully covered
            //     //         if ($scheduleItem->PAYMENT_VALUE >= $installment + ($arrears->PAST_DUE_PENALTY ?? 0)) {
            //     //             $scheduleItem->PAID_FLAG = 'PAID';
            //     //         }
            //     //     }
                
            //     //     // Calculate beforePastDue (without deducting penalty from installment if payment is insufficient)
            //     //     if ($arrears) {
            //     //         $beforePastDue = $scheduleItem->PAYMENT_VALUE - $penaltyPaid; // Do not reduce by penalty if insufficient
            //     //     } else {
            //     //         $beforePastDue = $scheduleItem->PAYMENT_VALUE;
            //     //     }
                
            //     //     // Calculate values for after_payment and penalty (denda)
            //     //     $after_value = intval($scheduleItem->PAYMENT_VALUE - $initialPaymentValue);
            //     //     $denda = $after_value - $beforePastDue;
                
            //     //     // Store the current schedule details
            //     //     $schedule[] = [
            //     //         'key' => $j++,
            //     //         'id_structur' => $scheduleItem->INSTALLMENT_COUNT . '-' . $after_value,
            //     //         'angsuran_ke' => $scheduleItem->INSTALLMENT_COUNT,
            //     //         'loan_number' => $scheduleItem->LOAN_NUMBER,
            //     //         'tgl_angsuran' => Carbon::parse($scheduleItem->PAYMENT_DATE)->format('d-m-Y'),
            //     //         'principal' => intval($scheduleItem->PRINCIPAL),
            //     //         'interest' => intval($scheduleItem->INTEREST),
            //     //         'installment' => intval($scheduleItem->INSTALLMENT),
            //     //         'principal_remains' => intval($scheduleItem->PRINCIPAL_REMAINS),
            //     //         'before_payment' => intval($initialPaymentValue),
            //     //         'after_payment' => $after_value,
            //     //         'bayar_angsuran' => $beforePastDue, // Reflect the full installment payment without penalty deduction if insufficient
            //     //         'bayar_denda' => $denda,
            //     //         'payment' => intval($scheduleItem->PAYMENT_VALUE),
            //     //         'flag' => $scheduleItem->PAID_FLAG,
            //     //         'denda' => intval($arrears->PAST_DUE_PENALTY ?? null)
            //     //     ];
            //     // }

            //     $paymentAmount = $request->jumlah_uang;

            //     $j = 0;
            //     foreach ($data as $scheduleItem) {
            //         $initialPaymentValue = $scheduleItem->PAYMENT_VALUE;
            //         $arrears = M_Arrears::where(['LOAN_NUMBER' => $scheduleItem->LOAN_NUMBER, 'START_DATE' => $scheduleItem->PAYMENT_DATE])->first();

            //         if ($paymentAmount > 0) {
            //             $installment = $scheduleItem->INSTALLMENT;
            //             $penalty = $arrears->PAST_DUE_PENALTY ?? 0;
            //             $totalDue = $installment + $penalty;

            //             if ($paymentAmount >= $totalDue) {
            //                 $scheduleItem->PAYMENT_VALUE += $totalDue;
            //                 $paymentAmount -= $totalDue;
            //                 $bayar_angsuran = $installment;
            //                 $bayar_denda = $penalty;
            //                 $scheduleItem->PAID_FLAG = 'PAID';
            //             } else {
            //                 $bayar_angsuran = min($paymentAmount, $installment);
            //                 $bayar_denda = min($paymentAmount - $bayar_angsuran, $penalty);
            //                 $scheduleItem->PAYMENT_VALUE += $bayar_angsuran + $bayar_denda;
            //                 $paymentAmount = 0;
            //                 $scheduleItem->PAID_FLAG = '';
            //             }
            //         } else {
            //             $bayar_angsuran = 0;
            //             $bayar_denda = 0;
            //             $scheduleItem->PAID_FLAG = '';
            //         }

            //         // Calculate beforePastDue (without deducting penalty from installment if payment is insufficient)
            //         if ($arrears) {
            //             $beforePastDue = $scheduleItem->PAYMENT_VALUE - $bayar_denda; // Do not reduce by penalty if insufficient
            //         } else {
            //             $beforePastDue = $scheduleItem->PAYMENT_VALUE;
            //         }

            //         // Calculate values for after_payment and penalty (denda)
            //         $after_value = intval($scheduleItem->PAYMENT_VALUE - $initialPaymentValue);
            //         $denda = $after_value - $beforePastDue;

            //         // Store the current schedule details
            //         $schedule[] = [
            //             'key' => $j++,
            //             'jumlah_uang' => $request->jumlah_uang,
            //             'id_structur' => $scheduleItem->INSTALLMENT_COUNT . '-' . $after_value,
            //             'angsuran_ke' => $scheduleItem->INSTALLMENT_COUNT,
            //             'loan_number' => $scheduleItem->LOAN_NUMBER,
            //             'tgl_angsuran' => Carbon::parse($scheduleItem->PAYMENT_DATE)->format('d-m-Y'),
            //             'principal' => intval($scheduleItem->PRINCIPAL),
            //             'interest' => intval($scheduleItem->INTEREST),
            //             'installment' => intval($scheduleItem->INSTALLMENT),
            //             'principal_remains' => intval($scheduleItem->PRINCIPAL_REMAINS),
            //             'before_payment' => intval($initialPaymentValue),
            //             'after_payment' => intval($after_value),
            //             'bayar_angsuran' => intval($bayar_angsuran),
            //             'bayar_denda' => intval($bayar_denda),
            //             'payment' => intval($scheduleItem->PAYMENT_VALUE),
            //             'flag' => $scheduleItem->PAID_FLAG,
            //             'denda' => intval($arrears->PAST_DUE_PENALTY ?? null)
            //         ];
            //     }

            //     // $paymentFor = [];
            //     // foreach ($schedule as $key => $value) {
            //     //     if ($value['flag'] == 'PAID') {
            //     //         $paymentFor[] = [
            //     //             'angsuran_ke' => $value['angsuran_ke'],
            //     //             'loan_number' => $value['loan_number'],
            //     //             'tgl_angsuran' => $value['tgl_angsuran'],
            //     //             'principal' => $value['principal'],
            //     //             'interest' => $value['interest'],
            //     //             'installment' => $value['installment'],
            //     //             'principal_remains' => $value['principal_remains'],
            //     //             'payment' => $value['payment'],
            //     //             'flag' => $value['flag']
            //     //         ];
            //     //     }
            //     // }

            //     // foreach ($schedule as $key => $value) {
            //     //     $schedule['payment_for'] = $paymentFor;
            //     // }
            // }else{
               
            // }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cekRO(Request $request)
    {
        try {
            $data = M_Customer::where('ID_NUMBER', $request->no_ktp)->get();

            $datas = $data->map(function($customer) {

                // $guarente_vehicle = DB::table('credit as a')
                //                     ->leftJoin('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                //                     ->leftJoin(DB::raw('
                //                         cr_collateral as latest'), function($join) {
                //                             $join->on('b.CR_CREDIT_ID', '=', 'latest.CR_CREDIT_ID')
                //                                 ->whereRaw('latest.CREATE_DATE = (SELECT MAX(CREATE_DATE) FROM cr_collateral WHERE CR_CREDIT_ID = b.CR_CREDIT_ID)');
                //                         })
                //                     ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
                //                     ->select('b.*', 'latest.CREATE_DATE as latest_created_date')
                //                     ->orderByDesc('latest.CREATE_DATE')
                //                     ->get();


                // $guarente_sertificat = DB::table('credit as a')
                //                         ->leftJoin('cr_collateral_sertification as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                //                         ->leftJoin(DB::raw('(SELECT CR_CREDIT_ID, MAX(CREATE_DATE) as latest_created_date
                //                                             FROM cr_collateral_sertification
                //                                             GROUP BY CR_CREDIT_ID) as latest'), function($join) {
                //                             $join->on('b.CR_CREDIT_ID', '=', 'latest.CR_CREDIT_ID');
                //                         })
                //                         ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
                //                         ->select('b.*', 'latest.latest_created_date')
                //                         ->orderBy('latest.latest_created_date', 'DESC')
                //                         ->get();

                $guarente_vehicle = DB::table('credit as a')
                                        ->leftJoin('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                                        ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
                                        ->where('a.CREATED_AT', '=', function ($query) {
                                            $query->select(DB::raw('MAX(CREATED_AT)'))
                                                ->from('credit');
                                        })
                                        ->select('b.*')
                                        ->get();

                $guarente_sertificat = DB::table('credit as a')
                                        ->leftJoin('cr_collateral_sertification as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                                        ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
                                        ->where('a.CREATED_AT', '=', function ($query) {
                                            $query->select(DB::raw('MAX(CREATED_AT)'))
                                                ->from('credit');
                                        })
                                        ->select('b.*')
                                        ->get();

                $jaminan = [];
            
                foreach ($guarente_vehicle as $guarantee) {
                    if (!empty($guarantee->ID)) {
                        $jaminan[] = [
                            "type" => "kendaraan",
                            'counter_id' => $guarantee->HEADER_ID,
                            "atr" => [
                                'id' => $guarantee->ID ?? null,
                                'status_jaminan' => null,
                                "tipe" => $guarantee->TYPE ?? null,
                                "merk" => $guarantee->BRAND ?? null,
                                "tahun" => $guarantee->PRODUCTION_YEAR ?? null,
                                "warna" => $guarantee->COLOR ?? null,
                                "atas_nama" => $guarantee->ON_BEHALF ?? null,
                                "no_polisi" => $guarantee->POLICE_NUMBER ?? null,
                                "no_rangka" => $guarantee->CHASIS_NUMBER ?? null,
                                "no_mesin" => $guarantee->ENGINE_NUMBER ?? null,
                                "no_bpkb" => $guarantee->BPKB_NUMBER ?? null,
                                "alamat_bpkb" => $guarantee->BPKB_ADDRESS ?? null,
                                "no_faktur" => $guarantee->INVOICE_NUMBER ?? null,
                                "no_stnk" => $guarantee->STNK_NUMBER ?? null,
                                "tgl_stnk" => $guarantee->STNK_VALID_DATE ?? null,
                                "nilai" => (int)($guarantee->VALUE ?? 0),
                                "document" => getCollateralDocument($guarantee->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']),
                            ]
                        ];
                    }
                }
                

                foreach ($guarente_sertificat as $list) {
                    if (!empty($list->ID)) {
                        $jaminan[] = [
                            "type" => "sertifikat",
                            'counter_id' => $list->HEADER_ID??null,
                            "atr" => [
                                'id' => $list->ID??null,
                                'status_jaminan' => null,
                                "no_sertifikat" => $list->NO_SERTIFIKAT??null,
                                "status_kepemilikan" => $list->STATUS_KEPEMILIKAN??null,
                                "imb" => $list->IMB??null,
                                "luas_tanah" => $list->LUAS_TANAH??null,
                                "luas_bangunan" => $list->LUAS_BANGUNAN??null,
                                "lokasi" => $list->LOKASI??null,
                                "provinsi" => $list->PROVINSI??null,
                                "kab_kota" => $list->KAB_KOTA??null,
                                "kec" => $list->KECAMATAN??null,
                                "desa" => $list->DESA??null,
                                "atas_nama" => $list->ATAS_NAMA??null,
                                "nilai" => (int)$list->NILAI??null,
                                "document" => getCollateralDocument($guarantee->ID, ['sertifikat'])
                            ]
                        ];
                    }
                }
            
                return [
                    'no_ktp' => $customer->ID_NUMBER??null,
                    'no_kk' => $customer->KK_NUMBER??null,
                    'nama' => $customer->NAME??null,
                    'tgl_lahir' => $customer->BIRTHDATE??null,
                    'alamat' => $customer->ADDRESS??null,
                    'rw' => $customer->RW??null,
                    'rt' => $customer->RT??null,
                    'provinsi' => $customer->PROVINCE?? null,
                    'city' => $customer->CITY?? null,
                    'kecamatan' => $customer->KECAMATAN?? null,
                    'kelurahan' => $customer->KELURAHAN?? null,
                    'kode_pos' => $customer->ZIP_CODE?? null,
                    'no_hp' => $customer->PHONE_PERSONAL??null,
                    "dokumen_indentitas" => M_CustomerDocument::where('CUSTOMER_ID',$customer->ID)->get(),
                    'jaminan' => $jaminan
                ];
            })->toArray();
            
            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

function getCollateralDocument($creditID, $param) {

    $documents = DB::table('cr_collateral_document')
                    ->whereIn('TYPE', $param)
                    ->where('COLLATERAL_ID', '=', $creditID)
                    ->get();     

    return $documents;
}

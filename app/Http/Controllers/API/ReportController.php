<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_Payment;
use App\Models\M_PaymentApproval;
use App\Models\M_PaymentAttachment;
use App\Models\M_PaymentDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function inquiryList(Request $request)
    {
        try {
            $mapping = [];

            if (empty($request->nama) && empty($request->no_kontrak)) {
                return response()->json($mapping, 200);
            } else {
                $query = DB::table('credit as a')
                            ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                            ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                            ->leftJoin('branch as d', 'd.ID', '=', 'a.BRANCH')
                            ->select(
                                'a.ID as creditId',
                                'a.LOAN_NUMBER', 
                                'a.ORDER_NUMBER', 
                                'b.ID as custId', 
                                'b.CUST_CODE', 
                                'b.NAME as customer_name',
                                'c.POLICE_NUMBER', 
                                'a.INSTALLMENT_DATE', 
                                'd.NAME as branch_name'
                            );
                
                if (!empty($request->no_kontrak)) {
                    $query->when($request->no_kontrak, function ($query, $no_kontrak) {
                        return $query->where('a.LOAN_NUMBER', 'LIKE', "%{$no_kontrak}%");
                    });
                }
            
                if (!empty($request->nama)) {
                    $query->when($request->nama, function ($query, $nama) {
                        return $query->where(DB::raw("CONCAT(b.NAME, ' ', b.ALIAS)"), 'LIKE', "%{$nama}%");
                    });
                }
            
                $results = $query->get();
            
                if (empty($results)) {
                    $mapping = [];
                } else {
                    $mapping = [];
                    foreach ($results as $result) {
                        $mapping[] = [
                            'credit_id' => $result->creditId ?? '',
                            'loan_number' => $result->LOAN_NUMBER ?? '',
                            'order_number' => $result->ORDER_NUMBER ?? '',
                            'cust_id' => $result->custId ?? '',
                            'cust_code' => $result->CUST_CODE ?? '',
                            'customer_name' => $result->customer_name ?? '',
                            'police_number' => $result->POLICE_NUMBER ?? '',
                            'entry_date' => date('Y-m-d', strtotime($result->INSTALLMENT_DATE)) ?? '',
                            'branch_name' => $result->branch_name ?? '',
                        ];
                    }
                }
            }
        
            return response()->json($mapping, 200);
            
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pinjaman(Request $request,$id)
    {
        try {
            $results = M_Credit::where('ID',$id)->first();

            if(!$results){
                $buildArray = [];
            }else{
                $buildArray =[
                    'status' => $results->STATUS??'',
                    'loan_number' => $results-> LOAN_NUMBER ?? '',
                    'cust_code' => $results-> CUST_CODE ?? '',
                    'branch_name' => M_Branch::find($results->BRANCH)->NAME??'',
                    'order_number' => $results-> ORDER_NUMBER ?? '',
                    'credit_type' => $results-> CREDIT_TYPE ?? '',
                    'tenor' => (int)$results-> PERIOD ?? 0,
                    'installment_date' => date('Y-m-d',strtotime($results->INSTALLMENT_DATE)) ?? '',
                    'installment' => floatval($results->INSTALLMENT) ?? 0,
                    'pcpl_ori' => floatval($results->PCPL_ORI) ?? 0,
                    'paid_principal' => floatval($results->PAID_PRINCIPAL) ?? 0,
                    'paid_interest' => floatval($results->PAID_INTEREST) ?? 0,
                    'paid_penalty' => floatval($results->PAID_PENALTY) ?? 0,
                    'mcf_name' => User::find($results->MCF_ID)->fullname??'',
                    'created_by' => User::find($results->CREATED_BY)->fullname??'',
                    'created_at' => date('Y-m-d',strtotime($results->CREATED_AT)) ??''
                ];
            }

            return response()->json($buildArray, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function debitur(Request $request,$id)
    {
        try {
            $results = DB::table('customer as a')
                        ->leftJoin('customer_extra as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                        ->select('a.*', 'b.*')
                        ->where('a.ID', $id)
                        ->first();

            if(!$results){
                $results = [];
            }else{
                $results = [
                    'pelanggan' => [
                        "nama" => $results->NAME ?? '',
                        "nama_panggilan" => $results->ALIAS ?? '',
                        "jenis_kelamin" => $results->GENDER ?? '',
                        "tempat_lahir" => $results->BIRTHPLACE ?? '',
                        "tgl_lahir" => date('Y-m-d',strtotime($results->BIRTHDATE)),
                        "gol_darah" => $results->BLOOD_TYPE ?? '',
                        "status_kawin" => $results->MARTIAL_STATUS ?? '',
                        "tgl_kawin" => $results->MARTIAL_DATE ?? '',
                        "tipe_identitas" => $results->ID_TYPE ?? '',
                        "no_identitas" => $results->ID_NUMBER ?? '',
                        "no_kk" => $results->KK_NUMBER ?? '',
                        "tgl_terbit_identitas" => date('Y-m-d', strtotime($results->ID_ISSUE_DATE))?? '',
                        "masa_berlaku_identitas" => date('Y-m-d', strtotime($results->ID_VALID_DATE)) ?? '',
                        "no_kk" => $results->KK_NUMBER??'',
                        "warganegara" => $results->CITIZEN ?? ''
                    ],
                    'alamat_identitas' => [
                        "alamat" => $results->ADDRESS ?? '',
                        "rt" => $results->RT ?? '',
                        "rw" => $results->RW ?? '',
                        "provinsi" => $results->PROVINCE ?? '',
                        "kota" => $results->CITY ?? '',
                        "kelurahan" => $results->KELURAHAN ?? '',
                        "kecamatan" => $results->KECAMATAN ?? '',
                        "kode_pos" => $results->ZIP_CODE ?? ''
                    ],
                    'alamat_tagih' => [
                        "alamat" => $results->INS_ADDRESS ?? '',
                        "rt" => $results->INS_RT ?? '',
                        "rw" => $results->INS_RW ?? '',
                        "provinsi" => $results->INS_PROVINCE ?? '',
                        "kota" => $results->INS_CITY ?? '',
                        "kelurahan" => $results->INS_KELURAHAN ?? '',
                        "kecamatan" => $results->INS_KECAMATAN ?? '',
                        "kode_pos" => $results->INS_ZIP_CODE ?? ''
                    ],
                    'pekerjaan' => [
                        "pekerjaan" => $results->OCCUPATION ?? '',
                        "pekerjaan_id" => $results->OCCUPATION_ON_ID ?? '',
                        "agama" => $results->RELIGION ?? '',
                        "pendidikan" => $results->EDUCATION ?? '',
                        "status_rumah" => $results->PROPERTY_STATUS ?? '',
                        "telepon_rumah" => $results->PHONE_HOUSE ?? '',
                        "telepon_selular" =>  $results->PHONE_PERSONAL ?? '',
                        "telepon_kantor" => $results->PHONE_OFFICE ?? '',
                        "ekstra1" => $results->EXT_1 ?? '',
                        "ekstra2" => $results->EXT_2 ?? ''
                    ],
                    'kerabat_darurat' => [
                        "nama"  => $results->EMERGENCY_NAME ?? '',
                        "alamat"  => $results->EMERGENCY_ADDRESS ?? '',
                        "rt"  => $results->EMERGENCY_RT ?? '',
                        "rw"  => $results->EMERGENCY_RW ?? '',
                        "provinsi" => $results->EMERGENCY_PROVINCE ?? '',
                        "kota" => $results->EMERGENCY_CITY ?? '',
                        "kelurahan" => $results->EMERGENCY_KELURAHAN ?? '',
                        "kecamatan" => $results->EMERGENCY_KECAMATAN ?? '',
                        "kode_pos" => $results->EMERGENCY_ZIP_CODE ?? '',
                        "no_telp" => $results->EMERGENCY_PHONE_HOUSE ?? '',
                        "no_hp" => $results->EMERGENCY_PHONE_PERSONAL ?? '',
                    ]
                ];
            }

            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function jaminan(Request $request,$id)
    {
        try {
            $collaterals = [];

            $collateral = M_CrCollateral::where('CR_CREDIT_ID', $id)->get();

            if ($collateral->isNotEmpty()) {
                $collaterals = $collateral->map(function ($item) {
                    return [
                        'JENIS JAMINAN' => 'KENDARAAN',
                        'MERK' => $item['BRAND'],
                        'TIPE' => $item['TYPE'],
                        'TAHUN' => $item['PRODUCTION_YEAR'],
                        'WARNA' => $item['COLOR'],
                        'ATAS NAMA' => $item['ON_BEHALF'],
                        'NO POLISI' => $item['POLICE_NUMBER'],
                        'NO RANGKA' => $item['CHASIS_NUMBER'],
                        'NO MESIN' => $item['ENGINE_NUMBER'],
                        'NO BPKB' => $item['BPKB_NUMBER'],
                        'NO STNK' => $item['STNK_NUMBER'],
                        'HARGA JAMINAN' => number_format(floatval($item['VALUE'])),
                        'LOKASI' => M_Branch::find($item['LOCATION_BRANCH'])->NAME ?? '',
                    ];
                })->values()->toArray();
            } else {
                $col_sertificat = M_CrCollateralSertification::where('CR_CREDIT_ID', $id)->get();

                if ($col_sertificat->isNotEmpty()) {
                    $collaterals = $col_sertificat->map(function ($item) {
                        return [
                            'JENIS JAMINAN' => 'SERTIFIKAT',
                            'NO SERTIFIKAT' => $item['NO_SERTIFIKAT'],
                            'STATUS KEPEMILIKAN' => $item['STATUS_KEPEMILIKAN'],
                            'IMB' => $item['IMB'],
                            'LUAS TANAH' => $item['LUAS_TANAH'],
                            'LUAS BANGUNAN' => $item['LUAS_BANGUNAN'],
                            'LOKASI' => $item['LOKASI'],
                            'PROVINSI' => $item['PROVINSI'],
                            'KAB/KOTA' => $item['KAB_KOTA'],
                            'KECAMATAN' => $item['KECAMATAN'],
                            'KELURAHAN/DESA' => $item['DESA'],
                            'ATAS NAMA' => $item['ATAS_NAMA'],
                            'NILAI' => 'IDR ' . number_format(floatval($item['NILAI'])),
                            'LOKASI' => M_Branch::find($item['LOCATION'])->NAME ?? '',
                        ];
                    })->values()->toArray();
                }
            }

            return response()->json($collaterals, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function pembayaran(Request $request,$id)
    {
        try {
                $sql = " SELECT a.BRANCH, a.TITLE, a.LOAN_NUM, a.ENTRY_DATE, b.INSTALLMENT, a.INVOICE, a.STTS_RCRD, a.ORIGINAL_AMOUNT,
                            SUM(CASE WHEN d.ACC_KEYS = 'ANGSURAN_POKOK' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_POKOK', 
                            SUM(CASE WHEN d.ACC_KEYS = 'ANGSURAN_BUNGA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_BUNGA',
                            SUM(CASE WHEN d.ACC_KEYS = 'BAYAR_DENDA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_DENDA',
                            SUM(CASE WHEN d.ACC_KEYS = 'BAYAR PELUNASAN POKOK' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_PELUNASAN_POKOK', 
                            SUM(CASE WHEN d.ACC_KEYS = 'BAYAR PELUNASAN BUNGA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_PELUNASAN_BUNGA',
                            SUM(CASE WHEN d.ACC_KEYS = 'BAYAR PELUNASAN DENDA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'BAYAR_PELUNASAN_DENDA',
                            SUM(CASE WHEN d.ACC_KEYS = 'DISKON_POKOK' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'DISKON_POKOK',
                            SUM(CASE WHEN d.ACC_KEYS = 'DISKON_BUNGA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'DISKON_BUNGA',
                            SUM(CASE WHEN d.ACC_KEYS = 'DISKON_DENDA' THEN d.ORIGINAL_AMOUNT ELSE 0 END) AS 'DISKON_DENDA'
                        FROM payment a
                        INNER JOIN credit b ON b.LOAN_NUMBER = a.LOAN_NUM
                        INNER JOIN payment_detail d ON d.PAYMENT_ID = a.ID
                        WHERE a.LOAN_NUM = {$id}
                        GROUP BY a.BRANCH, a.TITLE, a.LOAN_NUM, a.ENTRY_DATE, b.INSTALLMENT, a.INVOICE, a.STTS_RCRD, a.ORIGINAL_AMOUNT
                        ORDER BY a.ENTRY_DATE DESC;
                        ";

            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {
                $allData[] = [
                    'CABANG' => M_Branch::find($result->BRANCH)->NAME ?? '',
                    'NO INVOICE' => $result->INVOICE ?? '',
                    'NO KONTRAK' => $result->LOAN_NUM ?? '',
                    'TGL BAYAR' => $result->ENTRY_DATE ?? '',
                    'ANGSURAN' => $result->TITLE ?? '',
                    'JUMLAH BAYAR' => number_format($result->ORIGINAL_AMOUNT ?? 0),
                    'BAYAR POKOK' => number_format($result->BAYAR_POKOK ?? 0),
                    'BAYAR BUNGA' => number_format($result->BAYAR_BUNGA ?? 0),
                    'BAYAR DENDA' => number_format($result->BAYAR_DENDA ?? 0),
                    'BAYAR PELUNASAN POKOK' => number_format($result->BAYAR_PELUNASAN_POKOK ?? 0),
                    'BAYAR PELUNASAN BUNGA' => number_format($result->BAYAR_PELUNASAN_BUNGA ?? 0),
                    'BAYAR PELUNASAN DENDA' => number_format($result->BAYAR_PELUNASAN_DENDA ?? 0),
                    'DISKON POKOK' => number_format($result->DISKON_POKOK ?? 0),
                    'DISKON BUNGA' => number_format($result->DISKON_BUNGA ?? 0),
                    'DISKON DENDA' => number_format($result->DISKON_DENDA ?? 0),
                    'STATUS' => $result->STTS_RCRD ?? '',
                ];
            }            
           
            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function tunggakkan(Request $request,$id)
    {
        try {
            $results = M_Arrears::where('LOAN_NUMBER', $id)->first();

            if (!$results) {
                $allData = [];
            } else {
                $allData = $results;
            }
           
            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function strukturCredit(Request $request,$id)
    {
        try {
            $schedule = [];

            $data = DB::table('credit_schedule as a')
                                ->leftJoin('arrears as c', function ($join) {
                                    $join->on('c.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                                        ->whereColumn('c.START_DATE', '=', 'a.PAYMENT_DATE');
                                })
                                ->where('a.LOAN_NUMBER', '=', $id)
                                ->distinct() 
                                ->select(
                                    'a.INSTALLMENT_COUNT',
                                    'a.PAYMENT_DATE',
                                    'a.PRINCIPAL',
                                    'a.INTEREST',
                                    'a.INSTALLMENT',
                                    'a.PAYMENT_VALUE_PRINCIPAL',
                                    'a.PAYMENT_VALUE_INTEREST',
                                    'a.INSUFFICIENT_PAYMENT',
                                    'a.PAYMENT_VALUE',
                                    'a.PAID_FLAG',
                                    'c.PAST_DUE_PENALTY',
                                    'c.PAID_PENALTY'
                                )
                                ->orderBy('a.PAYMENT_DATE', 'asc')
                                ->get();

            if ($data->isEmpty()) {
                return $schedule;
            }

            foreach ($data as $res) {

                $getLastPayment = M_payment::select('ENTRY_DATE', 'LOAN_NUM', 'START_DATE')
                                            ->where('LOAN_NUM', $id)
                                            ->where('START_DATE', $res->PAYMENT_DATE)
                                            ->orderByDesc('ENTRY_DATE')
                                            ->first();

                $schedule[] = [
                    'Angs' => $res->INSTALLMENT_COUNT,
                    'Jt.Tempo' => Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y'),
                    'Tgl Bayar' => $getLastPayment ? Carbon::parse($getLastPayment->ENTRY_DATE??'')->format('d-m-Y'):'',
                    'Angs Pokok' => number_format($res->PRINCIPAL),
                    'Angs Bunga' => number_format($res->INTEREST),
                    'Angs Denda' => number_format($res->PAST_DUE_PENALTY),
                    'Ttl Angs' => number_format($res->INSTALLMENT),
                    'Bayar Pokok' => number_format($res->PAYMENT_VALUE_PRINCIPAL),
                    'Bayar Bunga' => number_format($res->PAYMENT_VALUE_INTEREST),
                    'Bayar Denda' => number_format($res->PAID_PENALTY),
                    'Kurang Bayar' => number_format($res->INSUFFICIENT_PAYMENT),
                    'Ttl Bayar' => number_format($res->PAYMENT_VALUE),
                    'Status' => $res->PAID_FLAG == 'PAID' ? 'LUNAS' : ''
                ];
            }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

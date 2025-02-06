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

            if (!isset($request->nama) && !isset($request->no_kontrak)) {
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
                        return $query->where("a.LOAN_NUMBER", 'LIKE', "%{$no_kontrak}%");
                    });
                }
            
                if (!empty($request->nama)) {
                    $query->when($request->nama, function ($query, $nama) {
                        return $query->where("b.NAME", 'LIKE', "%{$nama}%");
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
                $buildArray = [
                    [
                        'title' => 'Status',
                        'value' => $results->STATUS ?? ''
                    ],
                    [
                        'title' => 'No Kontrak',
                        'value' => $results->LOAN_NUMBER ?? ''
                    ],
                    [
                        'title' => 'No Customer',
                        'value' => $results->CUST_CODE ?? ''
                    ],
                    [
                        'title' => 'Branch Name',
                        'value' => M_Branch::find($results->BRANCH)->NAME ?? ''
                    ],
                    [
                        'title' => 'Order Number',
                        'value' => $results->ORDER_NUMBER ?? ''
                    ],
                    [
                        'title' => 'Tipe Kredit',
                        'value' => $results->CREDIT_TYPE ?? ''
                    ],
                    [
                        'title' => 'Tenor',
                        'value' => (int)$results->PERIOD ?? 0
                    ],
                    [
                        'title' => 'Tgl Angsuran',
                        'value' => date('Y-m-d', strtotime($results->INSTALLMENT_DATE)) ?? ''
                    ],
                    [
                        'title' => 'Angsuran',
                        'value' => floatval($results->INSTALLMENT) ?? 0
                    ],
                    [
                        'title' => 'Ttl Pinjaman',
                        'value' => floatval($results->PCPL_ORI) ?? 0
                    ],
                    [
                        'title' => 'Byr Pokok',
                        'value' => floatval($results->PAID_PRINCIPAL) ?? 0
                    ],
                    [
                        'title' => 'Byr Bunga',
                        'value' => floatval($results->PAID_INTEREST) ?? 0
                    ],
                    [
                        'title' => 'Byr Denda',
                        'value' => floatval($results->PAID_PENALTY) ?? 0
                    ],
                    [
                        'title' => 'Nama MCF',
                        'value' => User::find($results->MCF_ID)->fullname ?? ''
                    ],
                    [
                        'title' => 'Dibuat Oleh',
                        'value' => User::find($results->CREATED_BY)->fullname ?? ''
                    ],
                    [
                        'title' => 'Tgl Buat',
                        'value' => date('Y-m-d', strtotime($results->CREATED_AT)) ?? ''
                    ]
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
            $sql = "    SELECT a.BRANCH, a.TITLE, a.LOAN_NUM, a.ENTRY_DATE, b.INSTALLMENT, a.INVOICE, a.STTS_RCRD, a.ORIGINAL_AMOUNT,a.USER_ID
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
                        LEFT JOIN payment_detail d ON d.PAYMENT_ID = a.ID
                        WHERE a.LOAN_NUM = {$id}
                        GROUP BY a.BRANCH, a.TITLE, a.LOAN_NUM, a.ENTRY_DATE, b.INSTALLMENT, a.INVOICE, a.STTS_RCRD, a.ORIGINAL_AMOUNT
                        ORDER BY a.ENTRY_DATE DESC;
                        ";

            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {

                $getPosition = User::where('id', $result->USER_ID)->first();

                $allData[] = [
                    'Cbang' => M_Branch::find($result->BRANCH)->NAME ?? '',
                    'Metode Byr' => $getPosition? $getPosition->position??'':$result->PAYMENT_METHOD ?? '',
                    'No Inv' => $result->INVOICE ?? '',
                    'No Kont' => $result->LOAN_NUM ?? '',
                    'Tgl Byr' => $result->ENTRY_DATE ?? '',
                    'Angs' => $result->TITLE ?? '',
                    'Jml Byr' => number_format($result->ORIGINAL_AMOUNT ?? 0),
                    'Byr Pkk' => number_format($result->BAYAR_POKOK ?? 0),
                    'Byr Bnga' => number_format($result->BAYAR_BUNGA ?? 0),
                    'Byr Dnda' => number_format($result->BAYAR_DENDA ?? 0),
                    'Byr Plsn Pkk' => number_format($result->BAYAR_PELUNASAN_POKOK ?? 0),
                    'Byr Plsn Bnga' => number_format($result->BAYAR_PELUNASAN_BUNGA ?? 0),
                    'Byr Plsn Dnda' => number_format($result->BAYAR_PELUNASAN_DENDA ?? 0),
                    'Dskn Pkk' => number_format($result->DISKON_POKOK ?? 0),
                    'Dskn Bnga' => number_format($result->DISKON_BUNGA ?? 0),
                    'Dskn Dnda' => number_format($result->DISKON_DENDA ?? 0),
                    'Stts' => $result->STTS_RCRD ?? '',
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
            $results = M_Arrears::where('LOAN_NUMBER', $id)->get();

            if ($results->isEmpty()) {
                $allData = [];
            } else {
                $allData = [];

                foreach ($results as $res) {
                    $allData[] = [
                        'Jt.Tempo' => Carbon::parse($res->START_DATE)->format('Y-m-d'),
                        'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('Y-m-d') : '',
                        'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
                        'Bayar Dnda' => number_format($res->PAID_PENALTY ?? 0),
                        'Diskon Dnda' => number_format($res->WOFF_PENALTY ?? 0),
                        'Status' => $res->STATUS_REC == 'A' ? '':'LUNAS',
                    ];
                }
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
            $schedule = [
                'detail' => [],
                'data_credit' => []
            ];

            $sql = "    SELECT 
                            a.INSTALLMENT_COUNT, 
                            a.PAYMENT_DATE, 
                            a.PRINCIPAL, 
                            a.INTEREST, 
                            a.INSTALLMENT, 
                            a.PAYMENT_VALUE_PRINCIPAL, 
                            a.PAYMENT_VALUE_INTEREST, 
                            a.INSUFFICIENT_PAYMENT,
                            a.PAYMENT_VALUE,
                            a.PAID_FLAG, 
                            c.PAST_DUE_PENALTY, 
                            c.PAID_PENALTY, 
                            c.STATUS_REC, 
                            mp.ENTRY_DATE,
                            mp.INST_COUNT, 
                            case when a.PAID_FLAG <> 'PAID' and date_format(a.PAYMENT_DATE,'%d%m%Y') < date_format(now(),'%d%m%Y') then datediff(date_format(now(),'%d%m%Y'),date_format(a.PAYMENT_DATE,'%d%m%Y')) else 0 end as OD
                        from 
                            credit_schedule as a
                        left join 
                            arrears as c 
                            on c.LOAN_NUMBER = a.LOAN_NUMBER 
                            and c.START_DATE = a.PAYMENT_DATE
                        left join (
                            SELECT 	LOAN_NUM, 
                                    ENTRY_DATE,
                                    max(START_DATE) as START_DATE, 
                                    count(START_DATE) as INST_COUNT
                            FROM payment 
                            WHERE LOAN_NUM = '$id'
                            group by  LOAN_NUM,date_format(START_DATE,'%d%m%Y'),ENTRY_DATE
                        ) as mp 
                        on mp.LOAN_NUM = a.LOAN_NUMBER
                        and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
                        where 
                            a.LOAN_NUMBER = '$id'
                        order by a.PAYMENT_DATE asc";
                       

            $data = DB::select($sql);

            if (empty($data)) {
                return $schedule;
            }

            foreach ($data as $res) {

                $ttlByr = floatval($res->PRINCIPAL + $res->INTEREST + $res->PAST_DUE_PENALTY);
                $ttlByrAll = floatval($res->PAYMENT_VALUE_PRINCIPAL + $res->PAYMENT_VALUE_INTEREST + $res->PAID_PENALTY);

                $getInvoice = M_Payment::where(['LOAN_NUM' => $id,'START_DATE' => $res->PAYMENT_DATE])
                            ->orderBy('ENTRY_DATE', 'desc')
                            ->select('INVOICE')
                            ->first();


                $schedule['data_credit'][] = [
                    'Jt.Tempo' => Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y'),
                    'Angs' => $res->INSTALLMENT_COUNT,
                    'Seq' => $res->INST_COUNT??0,
                    'Amt Angs' => number_format($res->INSTALLMENT ?? 0),
                    'No Ref' => $getInvoice->INVOICE??'',
                    'Bank' => '',
                    'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE??'')->format('d-m-Y'):'',
                    'Amt Bayar' => number_format($res->PAYMENT_VALUE??0),
                    'Sisa Angs' => number_format($res->INSUFFICIENT_PAYMENT ?? 0),
                    'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
                    'Byr Dnda' => number_format($res->PAID_PENALTY ?? 0),
                    'Sisa Byr Tgh' => number_format($ttlByr ?? 0 - $ttlByrAll ?? 0),
                    'Ovd' => $res->PAID_FLAG == 'PAID' && $res->STATUS_REC != 'A' ? 0 : $res->OD??0,
                    'Stts' => $res->PAID_FLAG == 'PAID' && $res->STATUS_REC != 'A' ? 'LUNAS' : ''
                ];
            }

            $creditDetail = M_Credit::with(['customer' => function($query) {
                $query->select('CUST_CODE', 'NAME');
            }])->where('LOAN_NUMBER', $id)->first();

            if ($creditDetail) {
                $schedule['detail'] = [
                    'no_kontrak' => $creditDetail->LOAN_NUMBER??'',
                    'tgl_kontrak' => Carbon::parse($creditDetail->INSTALLMENT_DATE)->format('d-m-Y'),
                    'nama' => $creditDetail->customer->NAME ?? '', 
                    'no_pel' => $creditDetail->CUST_CODE??'',
                   'status' => ($creditDetail->STATUS ?? '') == 'D' ? 'Tidak Aktif' : 'Aktif'
                ];
            }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

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
                        return $query->where("a.LOAN_NUMBER", $no_kontrak);
                    });
                }

                if (!empty($request->nama)) {
                    $query->when($request->nama, function ($query, $nama) {
                        return $query->where("b.NAME", 'LIKE', "%$nama%");
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
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function pinjaman(Request $request, $id)
    {
        try {
            $results = M_Credit::where('ID', $id)->first();

            if (!$results) {
                $buildArray = [];
            } else {
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
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function debitur(Request $request, $id)
    {
        try {
            $results = DB::table('customer as a')
                ->leftJoin('customer_extra as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->select('a.*', 'b.*')
                ->where('a.ID', $id)
                ->first();

            if (!$results) {
                $results = [];
            } else {
                $results = [
                    'pelanggan' => [
                        "nama" => $results->NAME ?? '',
                        "nama_panggilan" => $results->ALIAS ?? '',
                        "jenis_kelamin" => $results->GENDER ?? '',
                        "tempat_lahir" => $results->BIRTHPLACE ?? '',
                        "tgl_lahir" => date('Y-m-d', strtotime($results->BIRTHDATE)),
                        "gol_darah" => $results->BLOOD_TYPE ?? '',
                        "ibu_kandung" => $results->MOTHER_NAME ?? '',
                        "status_kawin" => $results->MARTIAL_STATUS ?? '',
                        "tgl_kawin" => $results->MARTIAL_DATE ?? '',
                        "tipe_identitas" => $results->ID_TYPE ?? '',
                        "no_identitas" => $results->ID_NUMBER ?? '',
                        "no_kk" => $results->KK_NUMBER ?? '',
                        "tgl_terbit_identitas" => date('Y-m-d', strtotime($results->ID_ISSUE_DATE)) ?? '',
                        "masa_berlaku_identitas" => date('Y-m-d', strtotime($results->ID_VALID_DATE)) ?? '',
                        "no_kk" => $results->KK_NUMBER ?? '',
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
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function jaminan(Request $request, $id)
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
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function pembayaran(Request $request, $id)
    {
        try {
            $sql = "    SELECT  a.BRANCH,
                                a.TITLE,
                                a.LOAN_NUM,
                                a.ENTRY_DATE,
                                b.INSTALLMENT,
                                a.INVOICE,
                                a.STTS_RCRD,
                                a.ORIGINAL_AMOUNT,
                                a.USER_ID,
                                a.PAYMENT_METHOD,
                                c.CREATED_BY,
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
                        LEFT JOIN kwitansi c ON c.NO_TRANSAKSI = a.INVOICE
                        LEFT JOIN payment_detail d ON d.PAYMENT_ID = a.ID
                        WHERE a.LOAN_NUM = '$id'
                        GROUP BY a.BRANCH, a.TITLE, a.LOAN_NUM, a.ENTRY_DATE, b.INSTALLMENT, a.INVOICE, a.STTS_RCRD,
                                a.ORIGINAL_AMOUNT,a.USER_ID,a.PAYMENT_METHOD, c.CREATED_BY
                        ORDER BY a.ENTRY_DATE DESC;
                        ";

            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {

                $getPosition = User::where('username', $result->CREATED_BY)->first();

                $allData[] = [
                    'Cbang' => M_Branch::find($result->BRANCH)->NAME ?? '',
                    'Dibuat' => $getPosition->fullname ?? $result->CREATED_BY ?? '',
                    'Mtde Byr' => $getPosition ? $getPosition->position ?? '' : $result->PAYMENT_METHOD ?? '',
                    'No Inv' => $result->INVOICE ?? '',
                    'No Kont' => $result->LOAN_NUM ?? '',
                    'Tgl Byr' => $result->ENTRY_DATE ?? '',
                    'Angs' => $result->TITLE ?? '',
                    'Jml Byr' => number_format($result->ORIGINAL_AMOUNT ?? 0),
                    'Byr Angs' => number_format($result->BAYAR_POKOK ?? 0 + $result->BAYAR_BUNGA ?? 0),
                    'Byr Dnda' => number_format($result->BAYAR_DENDA ?? 0),
                    'Byr Plsn Ang' => number_format($result->BAYAR_PELUNASAN_POKOK ?? 0 + $result->BAYAR_PELUNASAN_BUNGA ?? 0),
                    'Byr Plsn Dnda' => number_format($result->BAYAR_PELUNASAN_DENDA ?? 0),
                    'Dskn Angs' => number_format($result->DISKON_POKOK ?? 0 + $result->DISKON_BUNGA ?? 0),
                    'Dskn Dnda' => number_format($result->DISKON_DENDA ?? 0),
                    'Stts' => $result->STTS_RCRD == 'PAID' ? 'SUCCESS' : $result->STTS_RCRD ?? '',
                ];
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function collateralReport(Request $request)
    {
        try {
            $sql = "SELECT	d.NAME as pos_pencairan, e.NAME as posisi_berkas,
                            b.LOAN_NUMBER as no_kontrak, c.NAME as debitur,
                            a.POLICE_NUMBER, a.STATUS
                    FROM	cr_collateral a
                            inner join credit b on b.ID = a.CR_CREDIT_ID
                            inner join customer c on c.CUST_CODE = b.CUST_CODE
                            left join branch d on d.ID = a.COLLATERAL_FLAG
                            left join branch e on e.ID = a.LOCATION_BRANCH
                            left join bpkb_detail f on f.COLLATERAL_ID = a.ID
                    WHERE	(1=1)
                            and d.NAME = 'filter pos'
                            and b.LOAN_NUMBER like '%$request->loan_number%'
                            and c.NAME like '%$request->nama%'
                            and a.POLICE_NUMBER like '%$request->nopol%'
                            and coalesce(f.STATUS,'Normal') = '$request->status'
                    ORDER	BY d.NAME, e.NAME, b.LOAN_NUMBER, c.NAME,
                            a.POLICE_NUMBER, a.STATUS";

            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {

                $allData[] = [
                    'pos_pencairan' => $result->pos_pencairan ?? '',
                    'posisi_berkas' => $result->posisi_berkas ?? '',
                    'no_kontrak' => $result->no_kontrak ?? '',
                    'nama_debitur' => $result->debitur ?? '',
                    'no_polisi' => $result->POLICE_NUMBER ?? '',
                    'status' => $result->STATUS ?? '',
                ];
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function tunggakkan(Request $request, $id)
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
                        'Status' => $res->STATUS_REC == 'A' ? '' : 'LUNAS',
                    ];
                }
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function strukturCredit(Request $request, $id)
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
                            mp.INST_COUNT_INCREMENT,
                            mp.ORIGINAL_AMOUNT,
                            mp.INVOICE,
                            mp.angsuran,
                            mp.denda,
                           CASE
                                WHEN c.PAST_DUE_PENALTY != 0 
                                THEN DATEDIFF(
                                            CASE
                                                WHEN mp.ENTRY_DATE IS NULL OR TRIM(mp.ENTRY_DATE) = '' THEN NOW()
                                                ELSE mp.ENTRY_DATE
                                            END,
                                           a.PAYMENT_DATE)
                                ELSE 0
                            END AS OD
                        from
                            credit_schedule as a
                        left join
                            arrears as c
                            on c.LOAN_NUMBER = a.LOAN_NUMBER
                            and c.START_DATE = a.PAYMENT_DATE
                        left join (
                            SELECT  
                                a.LOAN_NUM,
                                DATE(a.ENTRY_DATE) AS ENTRY_DATE, 
                                MAX(DATE(a.START_DATE)) AS START_DATE,
                                ROW_NUMBER() OVER (PARTITION BY a.START_DATE ORDER BY a.ENTRY_DATE) AS INST_COUNT_INCREMENT,
                                a.ORIGINAL_AMOUNT,
                                a.INVOICE,
                                b.angsuran,
                                b.denda
                            FROM 
                                payment a
                            LEFT JOIN (
                                SELECT  
                                    payment_id, 
                                    SUM(CASE WHEN ACC_KEYS = 'ANGSURAN_POKOK' OR ACC_KEYS = 'ANGSURAN_BUNGA' THEN ORIGINAL_AMOUNT ELSE 0 END) AS angsuran,
                                    SUM(CASE WHEN ACC_KEYS = 'BAYAR_DENDA' THEN ORIGINAL_AMOUNT ELSE 0 END) AS denda
                                FROM 
                                    payment_detail 
                                GROUP BY payment_id
                            ) AS b 
                            ON b.payment_id = a.id
                            WHERE 
                                a.LOAN_NUM = '11000240000426'
                            GROUP BY 
                                a.LOAN_NUM, a.START_DATE, a.ENTRY_DATE, a.ORIGINAL_AMOUNT, a.INVOICE, b.angsuran, b.denda
                            ORDER BY a.ENTRY_DATE DESC
                        ) as mp
                        on mp.LOAN_NUM = a.LOAN_NUMBER
                        and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
                        where
                            a.LOAN_NUMBER = '$id'
                        order by a.PAYMENT_DATE,mp.ENTRY_DATE asc";


            $data = DB::select($sql);

            if (empty($data)) {
                return $schedule;
            }

            $prevJtTempo = [];
            $prevAngs = [];

            foreach ($data as $res) {

                $ttlAngs = floatval($res->INSTALLMENT) + floatval($res->PAST_DUE_PENALTY);
                $ttlByr = floatval($res->angsuran) + floatval($res->denda);

                $sisaAngs = number_format(floatval($res->INSTALLMENT) - floatval($res->angsuran));

                $currentJtTempo = isset($res->PAYMENT_DATE) ? Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y') : '';
                $currentAngs = isset($res->INSTALLMENT_COUNT) ? $res->INSTALLMENT_COUNT : '';

                if (in_array($currentJtTempo, $prevJtTempo)) {
                    $currentJtTempo = '';
                } else {
                    array_push($prevJtTempo, $currentJtTempo);
                }

                if (in_array($currentAngs, $prevAngs)) {
                    $currentAngs = '';
                } else {
                    array_push($prevAngs, $currentAngs);
                }

                $sisaByr = number_format(abs($ttlAngs - $ttlByr));

                $schedule['data_credit'][] = [
                    'Jt.Tempo' => $currentJtTempo,
                    'Angs' => $currentAngs,
                    'Seq' => $res->INST_COUNT_INCREMENT ?? 0,
                    'Amt Angs' => number_format($res->INSTALLMENT ?? 0),
                    'No Ref' => $res->INVOICE ?? '',
                    'Bank' => '',
                    'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('d-m-Y') : '',
                    'Amt Bayar' => number_format($res->ORIGINAL_AMOUNT ?? 0),
                    'Sisa Angs' => $sisaAngs,
                    'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
                    'Byr Dnda' => number_format($res->denda ?? 0),
                    'Sisa Ttl Tghn' => number_format(abs($ttlAngs - $ttlByr)),
                    'Ovd' => $res->OD ?? 0,
                    'Stts' => $res->PAID_FLAG == 'PAID' && $res->STATUS_REC != 'A' ? 'LUNAS' : ''
                ];
            }

            $creditDetail = M_Credit::with(['customer' => function ($query) {
                $query->select('CUST_CODE', 'NAME');
            }])->where('LOAN_NUMBER', $id)->first();

            if ($creditDetail) {
                $schedule['detail'] = [
                    'no_kontrak' => $creditDetail->LOAN_NUMBER ?? '',
                    'tgl_kontrak' => Carbon::parse($creditDetail->INSTALLMENT_DATE)->format('d-m-Y'),
                    'nama' => $creditDetail->customer->NAME ?? '',
                    'no_pel' => $creditDetail->CUST_CODE ?? '',
                    'status' => ($creditDetail->STATUS ?? '') == 'D' ? 'Tidak Aktif' : 'Aktif'
                ];
            }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function collateralAllReport(Request $request)
    {
        try {
            $sql = "SELECT	d.NAME as pos_pencairan, e.NAME as posisi_berkas,
                            b.LOAN_NUMBER as no_kontrak, c.NAME as debitur,
                            a.POLICE_NUMBER, coalesce(f.STATUS,'NORMAL') as status
                    FROM	cr_collateral a
                            inner join credit b on b.ID = a.CR_CREDIT_ID
                            inner join customer c on c.CUST_CODE = b.CUST_CODE
                            left join branch d on d.ID = a.COLLATERAL_FLAG
                            left join branch e on e.ID = a.LOCATION_BRANCH
                            left join bpkb_detail f on f.COLLATERAL_ID = a.ID
                    WHERE	(1=1)";
            if ($request->pos && $request->pos != "SEMUA POS") {
                $sql .= "and d.NAME like '%$request->pos%'";
            }
            if ($request->loan_number) {
                $sql .= "and b.LOAN_NUMBER = '$request->loan_number'";
            }
            if ($request->nama) {
                $sql .= "and c.NAME like '%$request->nama%'";
            }
            if ($request->nopol) {
                $sql .= "and a.POLICE_NUMBER like '%$request->nopol%";
            }
            if ($request->status) {
                $sql .= "and coalesce(f.STATUS,'NORMAL') = '$request->status'";
            }

            $sql .= "ORDER	BY d.NAME, e.NAME, b.LOAN_NUMBER, c.NAME,
                            a.POLICE_NUMBER, f.STATUS ";


            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {

                $allData[] = [
                    'pos_pencairan' => $result->pos_pencairan ?? '',
                    'posisi_berkas' => $result->posisi_berkas ?? '',
                    'no_kontrak' => $result->no_kontrak ?? '',
                    'nama_debitur' => $result->debitur ?? '',
                    'no_polisi' => $result->POLICE_NUMBER ?? '',
                    'status' => $result->status ?? '',
                ];
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function kreditJatuhTempo(Request $request)
    {
        try {
            $filter = [];
            foreach ($request->hari as $stringHari) {
                array_push($filter, "date_format(date_add(now(),interval $stringHari day),'%d%m%Y')");
            }
            $imFilter = implode(',', $filter);
            $sql = "SELECT	d.NAME as CABANG,b.LOAN_NUMBER,c.NAME as DEBITUR,
                            a.PAYMENT_DATE,a.INSTALLMENT_COUNT,
                            a.PRINCIPAL-a.PAYMENT_VALUE_PRINCIPAL as POKOK,
                            a.INTEREST-a.PAYMENT_VALUE_INTEREST as BUNGA,
                            coalesce(e.TUNGG_POKOK,0) as TUNGGAKAN_POKOK,
                            coalesce(e.TUNGG_BUNGA,0) as TUNGGAKAN_BUNGA,
                            coalesce(e.DENDA,0) as DENDA,
                            coalesce(datediff(now(),e.TUNGG_AWAL),0) as HARI_TERLAMBAT,
                            c.ADDRESS,c.PHONE_PERSONAL
                    FROM	credit_schedule a
                            INNER JOIN credit b
                                on b.LOAN_NUMBER=a.LOAN_NUMBER
                                and b.STATUS='A'
                            INNER JOIN customer c on c.CUST_CODE=b.CUST_CODE
                            INNER JOIN branch d on d.ID=b.BRANCH
                            LEFT JOIN (	SELECT	s1.LOAN_NUMBER,
                                                sum(s1.PAST_DUE_PCPL-s1.PAID_PCPL) as TUNGG_POKOK,
                                                sum(s1.PAST_DUE_INTRST-s1.PAID_INT) as TUNGG_BUNGA,
                                                sum(s1.PAST_DUE_PENALTY-s1.PAID_PENALTY) as DENDA,
                                                min(s1.START_DATE) as TUNGG_AWAL
                                        FROM	arrears s1
                                        WHERE	s1.STATUS_REC='A'
                                        GROUP	BY s1.LOAN_NUMBER) e on e.LOAN_NUMBER=b.LOAN_NUMBER
                    WHERE	date_format(a.PAYMENT_DATE,'%d%m%Y')in ($imFilter)
                    and d.NAME like '%$request->cabang%'";
            // if ($request->pos && $request->pos != "SEMUA POS") {
            //     $sql .= "and d.NAME like '%$request->pos%'";
            // }
            // if ($request->loan_number) {
            //     $sql .= "and b.LOAN_NUMBER = '$request->loan_number'";
            // }
            // if ($request->nama) {
            //     $sql .= "and c.NAME like '%$request->nama%'";
            // }
            // if ($request->nopol) {
            //     $sql .= "and a.POLICE_NUMBER like '%$request->nopol%";
            // }
            // if ($request->status) {
            //     $sql .= "and coalesce(f.STATUS,'NORMAL') = '$request->status'";
            // }

            // $sql .= "ORDER	BY d.NAME, e.NAME, b.LOAN_NUMBER, c.NAME,
            //                 a.POLICE_NUMBER, f.STATUS ";


            $results = DB::select($sql);

            return response()->json($results, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

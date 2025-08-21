<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_DebiturReportAR;
use App\Http\Resources\R_Kwitansi;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function inquiryList(Request $request)
    {
        try {
            $mapping = [];

            if (!isset($request->nama) && !isset($request->no_kontrak) && !isset($request->no_polisi)) {
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

                if (!empty($request->no_polisi)) {
                    $query->when($request->no_polisi, function ($query, $no_polisi) {
                        return $query->where("c.POLICE_NUMBER", 'LIKE', "%$no_polisi%");
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

    public function setStatusNoActive($results)
    {
        $statusNoActive = '';

        switch ($results) {
            case 'CL':
                $statusNoActive = 'LUNAS NORMAL (CL)';
                break;
            case 'PT':
            case 'BL':
                $statusNoActive = 'LUNAS DIMUKA (' . $results . ')';
                break;
            case 'RP':
                $statusNoActive = 'REPOSSED (RP)';
                break;
            default:
                $statusNoActive = 'AKTIF (AC)';
                break;
        }

        return $statusNoActive;
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
                        'value' => $this->setStatusNoActive($results->STATUS_REC) ?? ''
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
            return $this->log->logError($e, $request);
        }
    }

    public function debitur(Request $request, $id)
    {
        try {
            $credit = M_Credit::with(['customer', 'customer.customer_extra'])->select('ID', 'LOAN_NUMBER', 'CUST_CODE', 'MCF_ID')->find($id);

            $result = $credit ? new R_DebiturReportAR($credit) : [];

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
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
                        'ALAMAT BPKB' => $item['BPKB_ADDRESS'],
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
            return $this->log->logError($e, $request);
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
            return $this->log->logError($e, $request);
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
            return $this->log->logError($e, $request);
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
            return $this->log->logError($e, $request);
        }
    }

    public function strukturCredit(Request $request, $id)
    {
        try {
            $schedule = [
                'detail' => [],
                'data_credit' => [],
                'total' => []
            ];

            $data = DB::select("CALL inquiry_details(?)", [$id]);

            if (empty($data)) {
                return $schedule;
            }

            $checkExist = [];
            $previousSisaAngs = 0;
            $setPinalty = 0;
            $setSisaDenda = 0;
            $ttlAmtAngs = 0;
            $ttlAmtBayar  = 0;
            $ttlDenda  = 0;
            $ttlBayarDenda  = 0;
            $sisaAngss = 0;

            foreach ($data as $res) {
                $currentJtTempo = isset($res->PAYMENT_DATE) ? Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y') : '';
                $currentAngs = isset($res->INSTALLMENT_COUNT) ? $res->INSTALLMENT_COUNT : '';

                $uniqArr = $currentJtTempo . '-' . $currentAngs;

                if (in_array($uniqArr, $checkExist)) {
                    $currentJtTempo = '';
                    $currentAngs = '';
                    $amtAngs = $sisaAngss;
                    $sisaAngs = max($previousSisaAngs - floatval($res->angsuran ?? 0), 0);

                    $setPinalty = floatval($setSisaDenda ?? 0);

                    $previousSisaAngs = $sisaAngs;
                } else {
                    $sisaAngs = max(floatval($res->INSTALLMENT ?? 0) - floatval($res->angsuran ?? 0), 0);
                    $previousSisaAngs = $sisaAngs;
                    $amtAngs = $res->INSTALLMENT;
                    $setPinalty = floatval($res->PAST_DUE_PENALTY ?? 0);
                    $setSisaDenda = floatval($res->PAST_DUE_PENALTY ?? 0) -  floatval($res->denda ?? 0);
                    array_push($checkExist, $uniqArr);

                    $ttlAmtAngs += $res->INSTALLMENT;
                    $ttlDenda  += $res->PAST_DUE_PENALTY;
                }

                $ttlBayarDenda  += $res->denda ?? 0;

                $amtBayar =  floatval($res->angsuran ?? 0);
                $sisaAngss = floatval($amtAngs ?? 0) - floatval($amtBayar ?? 0);

                $ttlAmtBayar += $amtBayar;

                $schedule['data_credit'][] = [
                    'Jt.Tempo' => $currentJtTempo,
                    'Angs' => $currentAngs,
                    'Seq' => $res->INST_COUNT_INCREMENT ?? 0,
                    'Amt Angs' => number_format($amtAngs ?? 0),
                    'No Ref' => $res->INVOICE ?? '',
                    'Bank' => '',
                    'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('d-m-Y') : '',
                    'Amt Bayar' => number_format($amtBayar ?? 0),
                    'Sisa Angs' => number_format($sisaAngss),
                    // 'Denda' => number_format($setPinalty),
                    'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
                    'Byr Dnda' => number_format($res->denda ?? 0),
                    'Sisa Tghn' => "0",
                    'Ovd' => $res->OD ?? 0
                ];
            }

            $schedule['total'] = [
                'ttlAmtAngs' => $ttlAmtAngs ?? '0',
                'ttlAmtBayar' => $ttlAmtBayar ?? '0',
                'ttlSisaAngs' => $ttlAmtAngs - $ttlAmtBayar ?? '0',
                'ttlDenda' => $ttlDenda ?? '0',
                'ttlBayarDenda' => $ttlBayarDenda ?? '0',
            ];

            $creditDetail = M_Credit::with(['customer' => function ($query) {
                $query->select('CUST_CODE', 'NAME');
            }])->where('LOAN_NUMBER', $id)->first();

            if ($creditDetail) {
                $schedule['detail'] = [
                    'no_kontrak' => $creditDetail->LOAN_NUMBER ?? '',
                    'tgl_kontrak' => Carbon::parse($creditDetail->CREATED_AT)->format('d-m-Y'),
                    'nama' => $creditDetail->customer->NAME ?? '',
                    'no_pel' => $creditDetail->CUST_CODE ?? '',
                    'status' => ($creditDetail->STATUS ?? '') == 'A' ? 'AKTIF' : 'TIDAK AKTIF / ' . $this->setStatusNoActive($creditDetail->STATUS_REC) ?? ''
                ];
            }

            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    // public function strukturCredit(Request $request, $id)
    // {
    //     try {
    //         $schedule = [
    //             'detail' => [],
    //             'data_credit' => [],
    //             'total' => []
    //         ];

    //         $data = DB::select("CALL inquiry_details(?)", [$id]);

    //         if (empty($data)) {
    //             return $schedule;
    //         }

    //         $credit = M_Credit::where('CREDIT_TYPE', 'bunga_menurun')->first();

    //         $checkExist = [];
    //         $previousSisaAngs = 0;
    //         $setPinalty = 0;
    //         $setSisaDenda = 0;
    //         $ttlAmtAngs = 0;
    //         $ttlAmtBayar  = 0;
    //         $ttlDenda  = 0;
    //         $ttlBayarDenda  = 0;
    //         $sisaAngss = 0;

    //         if (!$credit) {
    //             foreach ($data as $res) {
    //                 $currentJtTempo = isset($res->PAYMENT_DATE) ? Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y') : '';
    //                 $currentAngs = isset($res->INSTALLMENT_COUNT) ? $res->INSTALLMENT_COUNT : '';

    //                 $uniqArr = $currentJtTempo . '-' . $currentAngs;

    //                 if (in_array($uniqArr, $checkExist)) {
    //                     $currentJtTempo = '';
    //                     $currentAngs = '';
    //                     $amtAngs = $sisaAngss;
    //                     $sisaAngs = max($previousSisaAngs - floatval($res->angsuran ?? 0), 0);

    //                     $setPinalty = floatval($setSisaDenda ?? 0);

    //                     $previousSisaAngs = $sisaAngs;
    //                 } else {
    //                     $sisaAngs = max(floatval($res->INSTALLMENT ?? 0) - floatval($res->angsuran ?? 0), 0);
    //                     $previousSisaAngs = $sisaAngs;
    //                     $amtAngs = $res->INSTALLMENT;
    //                     $setPinalty = floatval($res->PAST_DUE_PENALTY ?? 0);
    //                     $setSisaDenda = floatval($res->PAST_DUE_PENALTY ?? 0) -  floatval($res->denda ?? 0);
    //                     array_push($checkExist, $uniqArr);

    //                     $ttlAmtAngs += $res->INSTALLMENT;
    //                     $ttlDenda  += $res->PAST_DUE_PENALTY;
    //                 }

    //                 $ttlBayarDenda  += $res->denda ?? 0;

    //                 $amtBayar =  floatval($res->angsuran ?? 0);
    //                 $sisaAngss = floatval($amtAngs ?? 0) - floatval($amtBayar ?? 0);

    //                 $ttlAmtBayar += $amtBayar;

    //                 $schedule['data_credit'][] = [
    //                     'Jt.Tempo' => $currentJtTempo,
    //                     'Angs' => $currentAngs,
    //                     'Seq' => $res->INST_COUNT_INCREMENT ?? 0,
    //                     'Amt Angs' => number_format($amtAngs ?? 0),
    //                     'No Ref' => $res->INVOICE ?? '',
    //                     'Bank' => '',
    //                     'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('d-m-Y') : '',
    //                     'Amt Bayar' => number_format($amtBayar ?? 0),
    //                     'Sisa Angs' => number_format($sisaAngss),
    //                     // 'Denda' => number_format($setPinalty),
    //                     'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
    //                     'Byr Dnda' => number_format($res->denda ?? 0),
    //                     'Sisa Tghn' => "0",
    //                     'Ovd' => $res->OD ?? 0
    //                 ];
    //             }
    //         } else {

    //             $totalPrincipal = array_sum(array_map(function ($item) {
    //                 return floatval($item->PRINCIPAL ?? 0);
    //             }, $data));

    //             foreach ($data as $res) {
    //                 $currentJtTempo = isset($res->PAYMENT_DATE) ? Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y') : '';
    //                 $currentAngs = isset($res->INSTALLMENT_COUNT) ? $res->INSTALLMENT_COUNT : '';

    //                 $uniqArr = $currentJtTempo . '-' . $currentAngs;

    //                 if (in_array($uniqArr, $checkExist)) {
    //                     $currentJtTempo = '';
    //                     $currentAngs = '';
    //                     $amtAngs = $sisaAngss;
    //                     $sisaAngs = max($previousSisaAngs - floatval($res->angsuran ?? 0), 0);

    //                     $setPinalty = floatval($setSisaDenda ?? 0);

    //                     $previousSisaAngs = $sisaAngs;
    //                 } else {
    //                     $sisaAngs = max(floatval($res->INSTALLMENT ?? 0) - floatval($res->angsuran ?? 0), 0);
    //                     $previousSisaAngs = $sisaAngs;
    //                     $amtAngs = $res->INSTALLMENT;
    //                     $setPinalty = floatval($res->PAST_DUE_PENALTY ?? 0);
    //                     $setSisaDenda = floatval($res->PAST_DUE_PENALTY ?? 0) -  floatval($res->denda ?? 0);
    //                     array_push($checkExist, $uniqArr);

    //                     $ttlAmtAngs += $res->INSTALLMENT;
    //                     $ttlDenda  += $res->PAST_DUE_PENALTY;
    //                 }

    //                 $ttlBayarDenda  += $res->denda ?? 0;

    //                 $amtBayar =  floatval($res->angsuran ?? 0);
    //                 $sisaAngss = floatval($amtAngs ?? 0) - floatval($amtBayar ?? 0);

    //                 $ttlAmtBayar += $amtBayar;

    //                 $schedule['data_credit'][] = [
    //                     'Jt.Tempo' => $currentJtTempo,
    //                     'Angs' => $currentAngs,
    //                     'Seq' => $res->INST_COUNT_INCREMENT ?? 0,
    //                     'Amt Angs' => number_format($totalPrincipal ?? 0),
    //                     'No Ref' => $res->INVOICE ?? '',
    //                     'Bank' => '',
    //                     'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('d-m-Y') : '',
    //                     'Amt Bayar' => number_format($amtBayar ?? 0),
    //                     'Sisa Angs' => number_format($sisaAngss),
    //                     // 'Denda' => number_format($setPinalty),
    //                     'Denda' => number_format($res->PAST_DUE_PENALTY ?? 0),
    //                     'Byr Dnda' => number_format($res->denda ?? 0),
    //                     'Sisa Tghn' => "0",
    //                     'Ovd' => $res->OD ?? 0
    //                 ];
    //             }
    //         }

    //         $schedule['total'] = [
    //             'ttlAmtAngs' => $ttlAmtAngs ?? '0',
    //             'ttlAmtBayar' => $ttlAmtBayar ?? '0',
    //             'ttlSisaAngs' => $ttlAmtAngs - $ttlAmtBayar ?? '0',
    //             'ttlDenda' => $ttlDenda ?? '0',
    //             'ttlBayarDenda' => $ttlBayarDenda ?? '0',
    //         ];

    //         $creditDetail = M_Credit::with(['customer' => function ($query) {
    //             $query->select('CUST_CODE', 'NAME');
    //         }])->where('LOAN_NUMBER', $id)->first();

    //         if ($creditDetail) {
    //             $schedule['detail'] = [
    //                 'no_kontrak' => $creditDetail->LOAN_NUMBER ?? '',
    //                 'tgl_kontrak' => Carbon::parse($creditDetail->CREATED_AT)->format('d-m-Y'),
    //                 'nama' => $creditDetail->customer->NAME ?? '',
    //                 'no_pel' => $creditDetail->CUST_CODE ?? '',
    //                 'status' => ($creditDetail->STATUS ?? '') == 'A' ? 'AKTIF' : 'TIDAK AKTIF / ' . $this->setStatusNoActive($creditDetail->STATUS_REC) ?? ''
    //             ];
    //         }

    //         return response()->json($schedule, 200);
    //     } catch (\Exception $e) {
    //         return $this->log->logError($e, $request);
    //     }
    // }

    function queryKapos($branchID)
    {
        $result = DB::table('users as a')
            ->select(
                'a.fullname',
                'a.position',
                'a.no_ktp',
                'a.alamat',
                'b.address',
                'b.name',
                'b.city'
            )
            ->leftJoin('branch as b', 'b.id', '=', 'a.branch_id')
            ->where('a.position', 'KAPOS')
            ->where('a.status', 'active')
            ->where('b.id', $branchID)
            ->first();

        return $result;
    }

    public function collateralAllReport(Request $request)
    {
        try {
            $sql = "SELECT	d.NAME as pos_pencairan, 
                            e.NAME as posisi_berkas,
                            b.LOAN_NUMBER as no_kontrak, 
                            c.NAME as debitur,
                            a.*, 
                            a.STATUS as status
                    FROM	cr_collateral a
                            inner join credit b on b.ID = a.CR_CREDIT_ID
                            inner join customer c on c.CUST_CODE = b.CUST_CODE
                            left join branch d on d.ID = a.COLLATERAL_FLAG
                            left join branch e on e.ID = a.LOCATION_BRANCH
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
                $sql .= "and a.POLICE_NUMBER like '%$request->nopol%'";
            }
            if ($request->status) {
                $sql .= "and a.STATUS = '" . strtoupper($request->status) . "'";
            }

            $sql .= "ORDER	BY d.NAME, e.NAME, b.LOAN_NUMBER, c.NAME, a.POLICE_NUMBER, a.STATUS ";

            $results = DB::select($sql);

            $dataDetail = $this->queryKapos($request->user()->branch_id);

            $allData = [];
            foreach ($results as $result) {

                $allData[] = [
                    'pos_pencairan' => $result->pos_pencairan ?? '',
                    'posisi_berkas' => $result->posisi_berkas ?? '',
                    'no_kontrak' => $result->no_kontrak ?? '',
                    'nama_debitur' => $result->debitur ?? '',
                    "tipe" => $result->TYPE,
                    "merk" => $result->BRAND,
                    "tahun" => $result->PRODUCTION_YEAR,
                    "warna" => $result->COLOR,
                    "atas_nama" => $result->ON_BEHALF,
                    'no_polisi' => $result->POLICE_NUMBER ?? '',
                    "no_rangka" => $result->CHASIS_NUMBER ?? '',
                    "no_mesin" => $result->ENGINE_NUMBER ?? '',
                    "no_bpkb" => $result->BPKB_NUMBER ?? '',
                    "alamat_bpkb" => $result->BPKB_ADDRESS ?? '',
                    "no_stnk" => $result->STNK_NUMBER ?? '',
                    "tgl_stnk" => $result->STNK_VALID_DATE ?? '',
                    "nilai" => (int) $result->VALUE ?? '',
                    'status' => $result->status ?? '',
                    "document" => $this->getCollateralDocument($result->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']) ?? null,
                    "document_rilis" => $this->attachmentRelease($result->ID, "'doc_rilis'") ?? null,
                    "kapos" => $dataDetail->fullname ?? null,
                    "nama_cabang" => $dataDetail->name ?? null,
                    "alamat_cabang" => $dataDetail->address ?? null,
                ];
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
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
            return $this->log->logError($e, $request);
        }
    }

    public function lapPembayaran(Request $request)
    {
        try {
            $dari = $request->dari;
            $cabang = $request->cabang_id;
            $getPosition = strtolower($request->user()->position);

            $data = M_Kwitansi::query();

            if ($getPosition === 'ho') {
                $data->orderBy('CREATED_AT', 'DESC');
            } else {
                $data->where('STTS_PAYMENT', 'PAID')->orderBy('CREATED_AT', 'DESC');
            }

            if (empty($dari) || $dari === 'null') {
                $date = Carbon::now()->format('Ymd');
            } else {
                $date = Carbon::parse($dari)->format('Ymd');
            }
            $data->where(DB::raw('DATE_FORMAT(CREATED_AT,"%Y%m%d")'), $date);

            if ($getPosition !== 'ho' && !empty($cabang)) {
                $data->where('BRANCH_CODE', $cabang);
            }

            $results = $data->get();
            $dto = R_Kwitansi::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function attachment($collateralId, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_collateral_document AS csd
                WHERE (TYPE, COUNTER_ID) IN (
                    SELECT TYPE, MAX(COUNTER_ID)
                    FROM cr_collateral_document
                    WHERE TYPE IN ($data)
                        AND COLLATERAL_ID = '$collateralId'
                    GROUP BY TYPE
                )
                ORDER BY COUNTER_ID DESC"
        );

        return $documents;
    }

    public function attachmentRelease($collateralId, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_collateral_document_release AS csd
                WHERE (TYPE, COUNTER_ID) IN (
                    SELECT TYPE, MAX(COUNTER_ID)
                    FROM cr_collateral_document_release
                    WHERE TYPE IN ($data)
                        AND COLLATERAL_ID = '$collateralId'
                    GROUP BY TYPE
                )
                ORDER BY COUNTER_ID DESC"
        );

        return $documents;
    }

    function getCollateralDocument($creditID, $param)
    {

        $documents = DB::table('cr_collateral_document')
            ->whereIn('TYPE', $param)
            ->where('COLLATERAL_ID', '=', $creditID)
            ->get();

        return $documents;
    }

    public function phonebookReport(Request $request)
    {
        try {
            $sql = "SELECT	d.NAME as pos_pencairan, 
                            e.NAME as posisi_berkas,
                            b.LOAN_NUMBER as no_kontrak, 
                            c.NAME as debitur,
                            a.*, 
                            a.STATUS as status
                    FROM	cr_collateral a
                            inner join credit b on b.ID = a.CR_CREDIT_ID
                            inner join customer c on c.CUST_CODE = b.CUST_CODE
                            left join branch d on d.ID = a.COLLATERAL_FLAG
                            left join branch e on e.ID = a.LOCATION_BRANCH
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
                $sql .= "and a.STATUS = '" . strtoupper($request->status) . "'";
            }

            $sql .= "ORDER	BY d.NAME, e.NAME, b.LOAN_NUMBER, c.NAME,
                            a.POLICE_NUMBER, a.STATUS ";


            $results = DB::select($sql);

            $allData = [];
            foreach ($results as $result) {

                $allData[] = [
                    'pos_pencairan' => $result->pos_pencairan ?? '',
                    'posisi_berkas' => $result->posisi_berkas ?? '',
                    'no_kontrak' => $result->no_kontrak ?? '',
                    'nama_debitur' => $result->debitur ?? '',
                    "tipe" => $result->TYPE,
                    "merk" => $result->BRAND,
                    "tahun" => $result->PRODUCTION_YEAR,
                    "warna" => $result->COLOR,
                    "atas_nama" => $result->ON_BEHALF,
                    'no_polisi' => $result->POLICE_NUMBER ?? '',
                    "no_rangka" => $result->CHASIS_NUMBER ?? '',
                    "no_mesin" => $result->ENGINE_NUMBER ?? '',
                    "no_bpkb" => $result->BPKB_NUMBER ?? '',
                    "no_stnk" => $result->STNK_NUMBER ?? '',
                    "tgl_stnk" => $result->STNK_VALID_DATE ?? '',
                    "nilai" => (int) $result->VALUE ?? '',
                    'status' => $result->status ?? '',
                    "document" => $this->getCollateralDocument($result->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']) ?? null,
                    "document_rilis" => $this->attachmentRelease($result->ID, "'doc_rilis'") ?? null,
                ];
            }

            return response()->json($allData, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}

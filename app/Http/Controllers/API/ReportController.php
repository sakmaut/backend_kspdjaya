<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_DebiturReportAR;
use App\Http\Resources\R_Kwitansi;
use App\Http\Resources\R_VisitReports;
use App\Models\M_Arrears;
use App\Models\M_Branch;
use App\Models\M_ClSurveyLogs;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_Credit;
use App\Models\M_CreditSchedule;
use App\Models\M_Customer;
use App\Models\M_Kwitansi;
use App\Models\TableViews\M_ColllectorVisits;
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

    // public function InquiryList(Request $request)
    // {
    //     try {
    //         $mapping = [];

    //         if (!isset($request->nama) && !isset($request->no_kontrak) && !isset($request->no_polisi)) {
    //             return response()->json($mapping, 200);
    //         } else {
    //             $query = DB::table('credit as a')
    //                 ->leftJoin('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
    //                 ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
    //                 ->leftJoin('branch as d', 'd.ID', '=', 'a.BRANCH')
    //                 ->select(
    //                     'a.ID as creditId',
    //                     'a.LOAN_NUMBER',
    //                     'a.ORDER_NUMBER',
    //                     'b.ID as custId',
    //                     'b.CUST_CODE',
    //                     'b.NAME as customer_name',
    //                     'c.POLICE_NUMBER',
    //                     'a.INSTALLMENT_DATE',
    //                     'd.NAME as branch_name'
    //                 );

    //             if (!empty($request->no_kontrak)) {
    //                 $query->when($request->no_kontrak, function ($query, $no_kontrak) {
    //                     return $query->where("a.LOAN_NUMBER", $no_kontrak);
    //                 });
    //             }

    //             if (!empty($request->nama)) {
    //                 $query->when($request->nama, function ($query, $nama) {
    //                     return $query->where("b.NAME", 'LIKE', "%$nama%");
    //                 });
    //             }

    //             if (!empty($request->no_polisi)) {
    //                 $query->when($request->no_polisi, function ($query, $no_polisi) {
    //                     return $query->where("c.POLICE_NUMBER", 'LIKE', "%$no_polisi%");
    //                 });
    //             }

    //             $results = $query->get();

    //             if (empty($results)) {
    //                 $mapping = [];
    //             } else {
    //                 $mapping = [];
    //                 foreach ($results as $result) {
    //                     $mapping[] = [
    //                         'credit_id' => $result->creditId ?? '',
    //                         'loan_number' => $result->LOAN_NUMBER ?? '',
    //                         'order_number' => $result->ORDER_NUMBER ?? '',
    //                         'cust_id' => $result->custId ?? '',
    //                         'cust_code' => $result->CUST_CODE ?? '',
    //                         'customer_name' => $result->customer_name ?? '',
    //                         'police_number' => $result->POLICE_NUMBER ?? '',
    //                         'entry_date' => date('Y-m-d', strtotime($result->INSTALLMENT_DATE)) ?? '',
    //                         'branch_name' => $result->branch_name ?? '',
    //                     ];
    //                 }
    //             }
    //         }

    //         return response()->json($mapping, 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    public function InquiryList(Request $request)
    {
        try {
            if (!collect($request->only(['nama', 'no_kontrak', 'no_polisi', 'cust_code']))
                ->filter(fn($v) => !is_null($v) && $v !== '')
                ->count()) {

                return response()->json([], 200);
            }

            $results = M_Credit::select([
                'ID',
                'LOAN_NUMBER',
                'STATUS',
                'ORDER_NUMBER',
                'CUST_CODE',
                'INSTALLMENT_DATE',
                'BRANCH',
                'CREATED_AT',
            ])
                ->with([
                    'customer:ID,CUST_CODE,NAME',
                    'collateral:CR_CREDIT_ID,POLICE_NUMBER',
                    'branch:ID,NAME'
                ])
                ->when(
                    $request->filled('no_kontrak'),
                    fn($q) =>
                    $q->where('LOAN_NUMBER', $request->no_kontrak)
                )
                ->when(
                    $request->filled('cust_code'),
                    fn($q) =>
                    $q->where('CUST_CODE', $request->cust_code)
                )
                ->when(
                    $request->filled('nama'),
                    fn($q) =>
                    $q->whereHas(
                        'customer',
                        fn($c) =>
                        $c->where('NAME', 'LIKE', "%{$request->nama}%")
                    )
                )
                ->when(
                    $request->filled('no_polisi'),
                    fn($q) =>
                    $q->whereHas(
                        'collateral',
                        fn($c) =>
                        $c->where('POLICE_NUMBER', 'LIKE', "%{$request->no_polisi}%")
                    )
                )
                ->get();

            $mapping = $results->map(fn($row) => [
                'credit_id'     => $row->ID,
                'status'     => $row->STATUS,
                'loan_number'   => $row->LOAN_NUMBER,
                'order_number'  => $row->ORDER_NUMBER,
                'cust_code'     => $row->CUST_CODE,
                'cust_id'       => $row->customer?->ID ?? '',
                'customer_name' => $row->customer?->NAME ?? '',
                'police_number' => $row->collateral?->POLICE_NUMBER ?? '',
                'entry_date'    => $row->INSTALLMENT_DATE
                    ? date('Y-m-d', strtotime($row->INSTALLMENT_DATE))
                    : '',
                'branch_name'   => $row->branch?->NAME ?? '',
                'created_at'   => Carbon::parse($row->CREATED_AT)->format("Y-m-d") ?? null,
            ])->values();

            return response()->json($mapping, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function CancelCredit(Request $request)
    {
        try {
            if (!collect($request->only(['nama', 'no_kontrak', 'no_polisi', 'cust_code']))
                ->filter(fn($v) => !is_null($v) && $v !== '')
                ->count()) {

                return response()->json([], 200);
            }

            $results = M_Credit::select([
                'ID',
                'LOAN_NUMBER',
                'STATUS',
                'ORDER_NUMBER',
                'CUST_CODE',
                'INSTALLMENT_DATE',
                'BRANCH',
                'CREATED_AT',
            ])
                ->with([
                    'customer:ID,CUST_CODE,NAME',
                    'collateral:CR_CREDIT_ID,POLICE_NUMBER',
                    'branch:ID,NAME'
                ])
                ->where('STATUS', 'A')
                ->when(
                    $request->filled('no_kontrak'),
                    fn($q) =>
                    $q->where('LOAN_NUMBER', $request->no_kontrak)
                )
                ->when(
                    $request->filled('cust_code'),
                    fn($q) =>
                    $q->where('CUST_CODE', $request->cust_code)
                )
                ->when(
                    $request->filled('nama'),
                    fn($q) =>
                    $q->whereHas(
                        'customer',
                        fn($c) =>
                        $c->where('NAME', 'LIKE', "%{$request->nama}%")
                    )
                )
                ->when(
                    $request->filled('no_polisi'),
                    fn($q) =>
                    $q->whereHas(
                        'collateral',
                        fn($c) =>
                        $c->where('POLICE_NUMBER', 'LIKE', "%{$request->no_polisi}%")
                    )
                )
                ->get();

            $mapping = $results->map(fn($row) => [
                'credit_id'     => $row->ID,
                'status'     => $row->STATUS,
                'loan_number'   => $row->LOAN_NUMBER,
                'order_number'  => $row->ORDER_NUMBER,
                'cust_code'     => $row->CUST_CODE,
                'cust_id'       => $row->customer?->ID ?? '',
                'customer_name' => $row->customer?->NAME ?? '',
                'police_number' => $row->collateral?->POLICE_NUMBER ?? '',
                'entry_date'    => Carbon::parse($row->CREATED_AT)->format("Y-m-d") ?? null,
                'branch_name'   => $row->branch?->NAME ?? '',
                'created_at'   => Carbon::parse($row->CREATED_AT)->format("Y-m-d") ?? null,
            ])->values();

            return response()->json($mapping, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500
            ], 500);
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
            case 'WO':
                $statusNoActive = 'BLACKLIST KONSUMEN';
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

            $credit = M_Credit::where('LOAN_NUMBER', $id)
                ->whereIn('CREDIT_TYPE', ['bunga_menurun'])
                ->first();

            $SetTotal =[];

            if (!$credit) {
                $data = DB::select("CALL inquiry_details(?)", [$id]);

                if (empty($data)) {
                    return $schedule;
                }

                $ttlAmtAngs    = 0;
                $ttlAmtBayar   = 0;
                $ttlDenda      = 0;
                $ttlBayarDenda = 0;

                $usedTempoAngs = [];

                foreach ($data as $res) {

                    $angs = $res->INSTALLMENT_COUNT ?? 0;
                    $tglTempo = $res->PAYMENT_DATE ?? '';
                    $tglTempoFormatted = $tglTempo
                        ? Carbon::parse($tglTempo)->format('d-m-Y')
                        : '';

                    $tglBayarFormatted = $res->ENTRY_DATE
                        ? Carbon::parse($res->ENTRY_DATE)->format('d-m-Y')
                        : '';

                    $amtAngs  = floatval($res->INSTALLMENT ?? 0);
                    $amtBayar = floatval($res->angsuran ?? 0);
                    $byrDenda = floatval($res->denda ?? 0);

                    $sisaAngs = max($amtAngs - $amtBayar, 0);

                    $uniqKey = $angs . '-' . $tglTempoFormatted;

                    $isFirstRow = !in_array($uniqKey, $usedTempoAngs);

                    if ($isFirstRow) {

                        $displayAngs     = $angs;
                        $displayTempo    = $tglTempoFormatted;
                        $displayAmtAngs  = number_format($amtAngs, 0);
                        $displayDenda    = number_format($res->PAST_DUE_PENALTY ?? 0, 0);

                        // Total hanya dihitung sekali
                        $ttlAmtAngs += $amtAngs;
                        $ttlDenda   += floatval($res->PAST_DUE_PENALTY ?? 0);

                        $usedTempoAngs[] = $uniqKey;

                        $schedule['data_credit'][] = [
                            'Angs'       => $displayAngs,
                            'Jt.Tempo'   => $displayTempo,
                            'Seq'        => $res->INST_COUNT_INCREMENT ?? 0,
                            'Amt Angs'   => $displayAmtAngs,
                            'Denda'      => $displayDenda,
                            'No Invoice' => $res->INVOICE ?? '',
                            'Tgl Bayar'  => $tglBayarFormatted,
                            'Amt Bayar'  => number_format($amtBayar, 0),
                            'Sisa Angs'  => number_format($sisaAngs, 0),
                            'Byr Dnda'   => number_format($byrDenda, 0),
                            'Ovd'        => $res->OD2 ?? 0
                        ];
                    } else {

                        if ($amtBayar != 0 || $byrDenda != 0) {

                            $schedule['data_credit'][] = [
                                'Angs'       => '',
                                'Jt.Tempo'   => '',
                                'Seq'        => '',
                                'Amt Angs'   => '',
                                'Denda'      => '',
                                'No Invoice' => $res->INVOICE ?? '',
                                'Tgl Bayar'  => $tglBayarFormatted,
                                'Amt Bayar'  => number_format($amtBayar, 0),
                                'Sisa Angs'  => '',
                                'Byr Dnda'   => number_format($byrDenda, 0),
                                'Ovd'        => $res->OD2 ?? 0
                            ];
                        }
                    }

                    $ttlAmtBayar   += $amtBayar;
                    $ttlBayarDenda += $byrDenda;
                }

                // foreach ($data as $res) {
                //     $currentJtTempo = isset($res->PAYMENT_DATE) ? Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y') : '';
                //     $currentAngs = isset($res->INSTALLMENT_COUNT) ? $res->INSTALLMENT_COUNT : '';

                //     $uniqArr = $currentJtTempo . '-' . $currentAngs;

                //     if (in_array($uniqArr, $checkExist)) {
                //         $currentJtTempo = '';
                //         $currentAngs = '';
                //         $amtAngs = $sisaAngss;
                //         $sisaAngs = max($previousSisaAngs - floatval($res->angsuran ?? 0), 0);
                //         $previousSisaAngs = $sisaAngs;
                //         $displayDenda = '';
                //     } else {
                //         $sisaAngs = max(floatval($res->INSTALLMENT ?? 0) - floatval($res->angsuran ?? 0), 0);
                //         $previousSisaAngs = $sisaAngs;
                //         $amtAngs = $res->INSTALLMENT;
                //         array_push($checkExist, $uniqArr);
                //         $displayDenda = number_format($res->PAST_DUE_PENALTY ?? 0);

                //         $ttlAmtAngs += $res->INSTALLMENT;
                //         $ttlDenda  += $res->PAST_DUE_PENALTY;
                //     }

                //     $ttlBayarDenda  += $res->denda ?? 0;

                //     $amtBayar =  floatval($res->angsuran ?? 0);
                //     $sisaAngss = floatval($amtAngs ?? 0) - floatval($amtBayar ?? 0);

                //     $ttlAmtBayar += $amtBayar;

                //     $schedule['data_credit'][] = [
                //         'Jt.Tempo' => $currentJtTempo,
                //         'Angs' => $currentAngs,
                //         'Seq' => $res->INST_COUNT_INCREMENT ?? 0,
                //         'Amt Angs' => number_format($amtAngs ?? 0),
                //         'Denda' => $displayDenda,
                //         'No Ref' => $res->INVOICE ?? '',
                //         'Tgl Bayar' => $res->ENTRY_DATE ? Carbon::parse($res->ENTRY_DATE ?? '')->format('d-m-Y') : '',
                //         'Amt Bayar' => number_format($amtBayar ?? 0),
                //         'Sisa Angs' => number_format($sisaAngss),
                //         'Byr Dnda' => number_format($res->denda ?? 0),
                //         'Sisa Tghn' => "0",
                //         'Ovd' => $res->OD ?? 0
                //     ];
                // }

                $SetTotal = [
                    '',
                    '',
                    '',
                    number_format($ttlAmtAngs ?? 0, 0, ',', '.'),
                    number_format($ttlDenda, 0, ',', '.'),
                    '',
                    '',
                    number_format($ttlAmtBayar ?? 0, 0, ',', '.'),
                    number_format($ttlAmtAngs - $ttlAmtBayar, 0, ',', '.'),
                    number_format($ttlBayarDenda, 0, ',', '.'),
                    '',
                ];
            } else {
                $data = DB::select("CALL inquiry_details_bunga_menurun(?)", [$id]);

                if (empty($data)) {
                    return $schedule;
                }

                $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $id)->get();

                $totalPrincipal = array_sum(array_map(function ($item) {
                    return floatval($item['PRINCIPAL'] ?? 0);
                }, $creditSchedule->toArray()));

                $data_credit = [];

                usort($data, function ($a, $b) {
                    return ($a->INSTALLMENT_COUNT ?? 0) <=> ($b->INSTALLMENT_COUNT ?? 0);
                });

                $sisaPokok = $totalPrincipal;
                $usedAngsuranTempo = [];
                $bungaDibayarPerKey = [];
                $data_credit = [];

                $ttlPokok = 0;
                $ttlBunga  = 0;
                $ttlDenda  = 0;
                $ttlByrPokok = 0;
                $ttlByrBunga  = 0;
                $ttlByrDenda  = 0;
                $ttlBayarDenda  = 0;

                foreach ($data as $res) {
                    $angs = $res->INSTALLMENT_COUNT ?? 0;
                    $tglTempo = $res->PAYMENT_DATE ?? '';
                    $tglTempoFormatted = $tglTempo ? Carbon::parse($tglTempo)->format('d-m-Y') : '';
                    $tglBayarFormatted = $res->tgl_byr ? Carbon::parse($res->tgl_byr)->format('d-m-Y') : '';

                    $byrPokok = floatval($res->bayar_pokok ?? 0);
                    $byrBunga = floatval($res->bayar_bunga ?? 0);
                    $byrDenda = floatval($res->bayar_denda ?? 0);
                    $interest = floatval($res->INTEREST ?? 0);

                    $ttlByrPokok += $byrPokok;
                    $ttlByrBunga += $byrBunga;
                    $ttlByrDenda += $byrDenda;

                    $uniqKey = $angs . '-' . $tglTempoFormatted;

                    if (!isset($bungaDibayarPerKey[$uniqKey])) {
                        $bungaDibayarPerKey[$uniqKey] = 0;
                    }

                    $interest = max(0, $interest - $bungaDibayarPerKey[$uniqKey]);
                    $bungaDibayarPerKey[$uniqKey] += $byrBunga;

                    $sisaPokok = max(0, $sisaPokok - $byrPokok);

                    $isFirstRow = !in_array($uniqKey, $usedAngsuranTempo);

                    if ($isFirstRow) {
                        $displayAngs     = $angs;
                        $displayTglTempo = $tglTempoFormatted;

                        $displayPokok = number_format($res->PRINCIPAL ?? 0, 0);
                        $displayBunga = number_format($interest, 0);
                        $displayDenda = number_format($res->PENALTY ?? 0, 0);

                        $ttlPokok += floatval($res->PRINCIPAL ?? 0);
                        $ttlBunga += $interest;
                        $ttlDenda += floatval($res->PENALTY ?? 0);

                        $usedAngsuranTempo[] = $uniqKey;

                        $data_credit[] = [
                            'Angs'      => $displayAngs,
                            'Jt.Tempo'  => $displayTglTempo,
                            'Pokok'     => $displayPokok,
                            'Bunga'     => $displayBunga,
                            'Denda'     => $displayDenda,
                            'Tgl Bayar' => $tglBayarFormatted,

                            'Byr Pokok' => $byrPokok > 0 ? number_format($byrPokok, 0) : 0,
                            'Byr Bunga' => $byrBunga > 0 ? number_format($byrBunga, 0) : 0,
                            'Byr Dnda'  => $byrDenda > 0 ? number_format($byrDenda, 0) : 0,

                            'Hari OD'   => $res->OD ?? 0
                        ];
                    } else {
                        if ($byrPokok != 0 || $byrBunga != 0 || $byrDenda != 0) {

                            $data_credit[] = [
                                'Angs'      => '',
                                'Jt.Tempo'  => '',
                                'Pokok'     => '',
                                'Bunga'     => '',
                                'Denda'     => '',
                                'Tgl Bayar' => $tglBayarFormatted,

                                'Byr Pokok' => $byrPokok > 0 ? number_format($byrPokok, 0) : 0,
                                'Byr Bunga' => $byrBunga > 0 ? number_format($byrBunga, 0) : 0,
                                'Byr Dnda'  => $byrDenda > 0 ? number_format($byrDenda, 0) : 0,

                                'Hari OD'   => $res->OD ?? 0
                            ];
                        }
                    }

                    // if (in_array($uniqKey, $usedAngsuranTempo)) {
                    //     $displayAngs = '';
                    //     $displayTglTempo = '';
                    //     $displayPokok = '';
                    //     $displayBunga = '';
                    //     $displayDenda = '';
                    // } else {
                    //     $displayAngs = $angs;
                    //     $displayTglTempo = $tglTempoFormatted;
                    //     $displayPokok = number_format($res->PRINCIPAL ?? 0, 0);
                    //     $displayBunga = number_format($interest, 0);
                    //     $displayDenda = number_format($res->PENALTY ?? 0, 0);

                    //     $ttlPokok += floatval($res->PRINCIPAL ?? 0);
                    //     $ttlBunga += $interest;
                    //     $ttlDenda += floatval($res->PENALTY ?? 0);

                    //     $usedAngsuranTempo[] = $uniqKey;
                    // }

                    // // if ($byrPokok != 0 || $byrBunga != 0 || $byrDenda != 0) {

                    // // }

                    // $data_credit[] = [
                    //     'Angs' => $displayAngs,
                    //     'Jt.Tempo' => $displayTglTempo,
                    //     'Pokok' => $displayPokok,
                    //     'Bunga' => $displayBunga,
                    //     'Denda' => $displayDenda,
                    //     'Tgl Bayar' => $tglBayarFormatted,
                    //     'Byr Pokok' => $byrPokok > 0 ? number_format($byrPokok, 0) : 0,
                    //     'Byr Bunga' => $byrBunga > 0 ? number_format($byrBunga, 0) : 0,
                    //     'Byr Dnda' => $byrDenda > 0 ? number_format($byrDenda, 0) : 0,
                    //     'Hari OD' => $res->OD ?? 0
                    // ];
                }

                $SetTotal = [
                    '',
                    '',
                    number_format($ttlPokok, 0, ',', '.'),
                    number_format($ttlBunga, 0, ',', '.'),
                    number_format($ttlDenda, 0, ',', '.'),
                    '',
                    number_format($ttlByrPokok, 0, ',', '.'),
                    number_format($ttlByrBunga, 0, ',', '.'),
                    number_format($ttlByrDenda, 0, ',', '.'),
                    '',
                ];

                $schedule['data_credit'] = $data_credit;
            }

            $schedule['total'] = $SetTotal;

            $creditDetail = M_Credit::with(['customer' => function ($query) {
                $query->select('CUST_CODE', 'NAME');
            }])->where('LOAN_NUMBER', $id)->first();

            $typeMap = [
                'bunga_menurun' => 'BUNGA MENURUN',
                'bulanan'       => 'BULANAN',
                'musiman'       => 'MUSIMAN',
            ];

            $type = $typeMap[$creditDetail->CREDIT_TYPE] ?? '';

            if ($creditDetail) {
                $schedule['detail'] = [
                    'no_kontrak' => $creditDetail->LOAN_NUMBER ?? '',
                    'tgl_kontrak' => isset($creditDetail->CREATED_AT) ? Carbon::parse($creditDetail->CREATED_AT)->format('d-m-Y') : '',
                    'nama' => $creditDetail->customer->NAME ?? '',
                    'no_pel' => $creditDetail->CUST_CODE ?? '',
                    'jns_credit' => $type,
                    'status' => ($creditDetail->STATUS ?? '') === 'A'
                        ? 'AKTIF'
                        : 'TIDAK AKTIF / ' . ($this->setStatusNoActive($creditDetail->STATUS_REC) ?? '')
                ];
            }


            return response()->json($schedule, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

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
                $date = Carbon::now('Asia/Jakarta')->format('Ymd');
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

    // public function LkbhReportsDownload(Request $request)
    // {
    //     try {
    //         $dari         = $request->dari;
    //         $sampai       = $request->sampai;
    //         $cabangId     = $request->cabang_id;
    //         $user         = $request->user();
    //         $position     = $user->position;
    //         $userBranchId = $user->branch_id;

    //         $branchParam = $position !== 'HO'
    //             ? $userBranchId
    //             : ($cabangId ?: null);

    //         $result = [
    //             "Title"   => "LAPORAN KEUANGAN BERBASIS HARIAN (LKBH)",
    //             "Tanggal" => "Tanggal {$dari} - {$sampai}",
    //             "Header"  => [
    //                 "Cabang",
    //                 "Tanggal Transaksi",
    //                 "Petugas",
    //                 "Jabatan",
    //                 "Nomor Kontrak",
    //                 "Diterima Dari",
    //                 "Keterangan",
    //                 "Metode Pembayaran",
    //                 "Nominal"
    //             ],
    //             "Data" => []
    //         ];

    //         $arusKas = DB::select("CALL sp_lkbh_report(?, ?, ?)", [$dari, $sampai, $branchParam]);

    //         $rows         = [];
    //         $tempAngsuran = [];
    //         $tempPembulatan = [];

    //         foreach ($arusKas as $item) {

    //             $invoice   = $item->INVOICE;
    //             $tgl       = date('Y-m-d', strtotime($item->ENTRY_DATE));
    //             $amount    = (float) $item->ORIGINAL_AMOUNT;
    //             $pelunasan = strtolower($item->PAYMENT_TYPE) === 'pelunasan'
    //                 ? "PELUNASAN"
    //                 : "CASH_IN";

    //             // ==========================
    //             // ANGSURAN
    //             // ==========================
    //             if (in_array($item->ACC_KEYS, ['ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])) {

    //                 if (!isset($rows[$invoice])) {
    //                     $rows[$invoice] = [
    //                         "type"   => "CASH_IN",
    //                         "cabang" => $item->BRANCH_NAME ?? "",
    //                         "tgl"    => $tgl,
    //                         "user"   => $item->fullname ?? "",
    //                         "position" => $item->position,
    //                         "no_kontrak" => $item->LOAN_NUM,
    //                         "nama_pelanggan" => $item->NAMA ?? "",
    //                         "keterangan" => "BAYAR " . strtoupper($item->JENIS) . " ({$invoice})",
    //                         "metode" => $item->PAYMENT_METHOD,
    //                         "amount" => 0
    //                     ];
    //                 }

    //                 $rows[$invoice]["amount"] += $amount;
    //             }

    //             // ==========================
    //             // DENDA
    //             // ==========================
    //             if ($item->ACC_KEYS === "BAYAR_DENDA" && $amount > 0) {
    //                 $rows[] = [
    //                     "type"   => $pelunasan,
    //                     "cabang" => $item->BRANCH_NAME ?? "",
    //                     "tgl"    => $tgl,
    //                     "user"   => $item->fullname ?? "",
    //                     "position" => $item->position,
    //                     "no_kontrak" => $item->LOAN_NUM,
    //                     "nama_pelanggan" => $item->NAMA ?? "",
    //                     "keterangan" => "BAYAR DENDA ({$invoice})",
    //                     "metode" => $item->PAYMENT_METHOD,
    //                     "amount" => $amount
    //                 ];
    //             }

    //             // ==========================
    //             // FEE BUNGA
    //             // ==========================
    //             if (in_array($item->ACC_KEYS, ['FEE_BUNGA', 'FEE_PROCCESS'])) {

    //                 $key = $invoice . '_' . $item->LOAN_NUM;

    //                 if (!isset($tempAngsuran[$key])) {
    //                     $tempAngsuran[$key] = [
    //                         "type"   => "FEE_BUNGA",
    //                         "cabang" => $item->BRANCH_NAME ?? "",
    //                         "tgl"    => $tgl,
    //                         "user"   => $item->fullname ?? "",
    //                         "position" => $item->position,
    //                         "no_kontrak" => $item->LOAN_NUM,
    //                         "nama_pelanggan" => $item->NAMA ?? "",
    //                         "keterangan" => "BAYAR FEE BUNGA MENURUN ({$invoice})",
    //                         "metode" => $item->PAYMENT_METHOD,
    //                         "amount" => 0
    //                     ];
    //                 }

    //                 $tempAngsuran[$key]["amount"] += $amount;
    //             }
    //         }

    //         foreach ($tempAngsuran as $feeRow) {
    //             $rows[] = $feeRow;
    //         }

    //         // ==========================
    //         // PENCAIRAN (CASH OUT)
    //         // ==========================
    //         $pencairan = DB::select("CALL sp_lkbh_pencairan(?, ?, ?)", [$dari, $sampai, $branchParam]);

    //         foreach ($pencairan as $item) {
    //             $rows[] = [
    //                 "type"   => "CASH_OUT",
    //                 "cabang" => $item->BRANCH_NAME ?? "",
    //                 "tgl"    => date('Y-m-d', strtotime($item->ENTRY_DATE)),
    //                 "user"   => $item->fullname ?? "",
    //                 "position" => $item->position,
    //                 "no_kontrak" => $item->LOAN_NUM,
    //                 "nama_pelanggan" => $item->CUSTOMER_NAME ?? "",
    //                 "keterangan" => "PENCAIRAN " . strtoupper($item->CREDIT_TYPE),
    //                 "metode" => "",
    //                 "amount" => (float) ($item->ORIGINAL_AMOUNT - $item->TOTAL_ADMIN)
    //             ];
    //         }

    //         $rows = array_values($rows);

    //         // ==========================
    //         // SORT
    //         // ==========================
    //         usort($rows, function ($a, $b) {
    //             return [$a["tgl"], $a["position"], $a["no_kontrak"]]
    //                 <=> [$b["tgl"], $b["position"], $b["no_kontrak"]];
    //         });

    //         // ==========================
    //         // FORMAT FINAL OUTPUT
    //         // ==========================
    //         foreach ($rows as $row) {

    //             $amountFormatted = fmod($row["amount"], 1) == 0
    //                 ? number_format($row["amount"], 0, ',', '.')
    //                 : number_format($row["amount"], 2, ',', '.');

    //             $result["Data"][] = [
    //                 $row["cabang"],
    //                 $row["tgl"],
    //                 $row["user"],
    //                 $row["position"],
    //                 $row["no_kontrak"],
    //                 $row["nama_pelanggan"],
    //                 $row["keterangan"],
    //                 $row["metode"],
    //                 $amountFormatted
    //             ];
    //         }

    //         return response()->json($result, 200);
    //     } catch (\Exception $e) {
    //         return $this->log->logError($e, $request);
    //     }
    // }

    public function LkbhReports(Request $request)
    {
        try {
            $dari         = $request->dari;
            $sampai       = $request->sampai;
            $cabangId     = $request->cabang_id;
            $user         = $request->user();
            $position     = $user->position;
            $userBranchId = $user->branch_id;

            $branchParam = $position !== 'HO' ? $userBranchId : ($cabangId ?: null);

            $result = [
                'dari'   => $dari ?? '',
                'sampai' => $sampai ?? '',
                'HeaderTitle' => [
                    "Title"   => "LAPORAN KEUANGAN BERBASIS HARIAN (LKBH)",
                    "Tanggal" => "Tanggal " . ($dari ?? '') . " - " . ($sampai ?? '')
                ],
                'HeaderTable' => [
                    "Cabang",
                    "Tanggal Transaksi",
                    "Petugas",
                    "Jabatan",
                    "Nomor Kontrak",
                    "Diterima Dari",
                    "Keterangan",
                    "Metode Pembayaran",
                    "Nominal"
                ],
            ];

            $arusKas = DB::select("CALL sp_lkbh_report(?, ?, ?)", [$dari, $sampai, $branchParam]);

            $rows         = [];
            $tempAngsuran = [];
            $tempPembulatan = [];
            $tempPelunasan   = [];
            $tempDenda = [];

            foreach ($arusKas as $item) {
                $invoice   = $item->INVOICE;
                $tgl       = date('Y-m-d', strtotime($item->ENTRY_DATE));
                $amount    = (float) $item->ORIGINAL_AMOUNT;
                $pelunasan = strtolower($item->PAYMENT_TYPE) === 'pelunasan' ? "PELUNASAN" : "CASH_IN";

                // ANGSURAN_POKOK & ANGSURAN_BUNGA → digabung per invoice
                if (in_array($item->ACC_KEYS, ['ANGSURAN_POKOK', 'ANGSURAN_BUNGA'])) {
                    if (!isset($rows[$invoice])) {
                        $rows[$invoice] = [
                            "type"              => "CASH_IN",
                            "no_invoice"        => $invoice,
                            "no_kontrak"        => $item->LOAN_NUM,
                            "tgl"               => $tgl,
                            "cabang"            => $item->BRANCH_NAME ?? "",
                            "user"              => $item->fullname ?? "",
                            "position"          => $item->position,
                            "nama_pelanggan"    => $item->NAMA ?? "",
                            "metode_pembayaran" => $item->PAYMENT_METHOD,
                            "keterangan"        => "BAYAR " . strtoupper($item->JENIS) . " ({$invoice})",
                            "amount"            => 0
                        ];
                    }
                    $rows[$invoice]["amount"] += $amount;
                }

                // BAYAR_DENDA
                if ($item->ACC_KEYS === "BAYAR_DENDA" && $amount > 0) {
                    $key = $invoice;

                    if (!isset($tempDenda[$key])) {
                        $tempDenda[$key] = [
                            "type"              => $pelunasan,
                            "no_invoice"        => $invoice,
                            "no_kontrak"        => $item->LOAN_NUM,
                            "tgl"               => $tgl,
                            "cabang"            => $item->BRANCH_NAME ?? "",
                            "user"              => $item->fullname ?? "",
                            "position"          => $item->position,
                            "nama_pelanggan"    => $item->NAMA ?? "",
                            "metode_pembayaran" => $item->PAYMENT_METHOD,
                            "keterangan"        => "BAYAR DENDA (" . $invoice . ")",
                            "amount"            => 0
                        ];
                    }

                    $tempDenda[$key]["amount"] += $amount;
                }

                if (in_array($item->ACC_KEYS, ['BAYAR_POKOK', 'BAYAR_BUNGA'])) {

                    $key = $invoice;

                    if (!isset($tempPelunasan[$key])) {
                        $tempPelunasan[$key] = [
                            "type"              => "PELUNASAN",
                            "no_invoice"        => $invoice,
                            "no_kontrak"        => $item->LOAN_NUM,
                            "tgl"               => $tgl,
                            "cabang"            => $item->BRANCH_NAME ?? "",
                            "user"              => $item->fullname ?? "",
                            "position"          => $item->position,
                            "nama_pelanggan"    => $item->NAMA ?? "",
                            "metode_pembayaran" => $item->PAYMENT_METHOD,
                            "keterangan"        => "BAYAR PELUNASAN ({$invoice})",
                            "amount"            => 0
                        ];
                    }

                    $tempPelunasan[$key]["amount"] += $amount;
                }

                // BAYAR_PELUNASAN_PINALTY
                if ($item->ACC_KEYS === "BAYAR PELUNASAN PINALTY" && $amount > 0) {
                    $rows[] = [
                        "type"              => "PELUNASAN",
                        "no_invoice"        => $invoice,
                        "no_kontrak"        => $item->LOAN_NUM,
                        "tgl"               => $tgl,
                        "cabang"            => $item->BRANCH_NAME ?? "",
                        "user"              => $item->fullname ?? "",
                        "position"          => $item->position,
                        "nama_pelanggan"    => $item->NAMA ?? "",
                        "metode_pembayaran" => $item->PAYMENT_METHOD,
                        "keterangan"        => "BAYAR PELUNASAN PINALTY ({$invoice})",
                        "amount"            => $amount
                    ];
                }

                // PEMBULATAN
                if (!empty($item->PEMBULATAN) && (float) $item->PEMBULATAN > 0 && !isset($tempPembulatan[$invoice])) {
                    $tempPembulatan[$invoice] = true;
                    $rows[] = [
                        "type"              => $pelunasan,
                        "no_invoice"        => $invoice,
                        "no_kontrak"        => $item->LOAN_NUM,
                        "tgl"               => $tgl,
                        "cabang"            => $item->BRANCH_NAME ?? "",
                        "user"              => $item->fullname ?? "",
                        "position"          => $item->position,
                        "nama_pelanggan"    => $item->NAMA ?? "",
                        "metode_pembayaran" => $item->PAYMENT_METHOD,
                        "keterangan"        => "PEMBULATAN ({$invoice})",
                        "amount"            => (float) $item->PEMBULATAN
                    ];
                }

                // FEE_BUNGA & FEE_PROCCESS → type khusus "FEE_BUNGA", digabung per invoice+loan
                if (in_array($item->ACC_KEYS, ['FEE_BUNGA', 'FEE_PROCCESS'])) {
                    $key = $invoice . '_' . $item->LOAN_NUM;

                    if (!isset($tempAngsuran[$key])) {
                        $tempAngsuran[$key] = [
                            "type"              => "FEE_BUNGA",
                            "no_invoice"        => $invoice,
                            "no_kontrak"        => $item->LOAN_NUM,
                            "tgl"               => $tgl,
                            "cabang"            => $item->BRANCH_NAME ?? "",
                            "user"              => $item->fullname ?? "",
                            "position"          => $item->position,
                            "nama_pelanggan"    => $item->NAMA ?? "",
                            "metode_pembayaran" => $item->PAYMENT_METHOD,
                            "keterangan"        => "BAYAR FEE BUNGA MENURUN ({$invoice})",
                            "amount"            => 0
                        ];
                    }

                    $tempAngsuran[$key]["amount"] += $amount;
                }
            }

            foreach ($tempPelunasan as $rowPelunasan) {
                $rows[] = $rowPelunasan;
            }

            foreach ($tempDenda as $rowDenda) {
                $rows[] = $rowDenda;
            }

            foreach ($tempAngsuran as $feeRow) {
                $rows[] = $feeRow;
            }

            // CASH_OUT dari pencairan
            $pencairan = DB::select("CALL sp_lkbh_pencairan(?, ?, ?)", [$dari, $sampai, $branchParam]);

            foreach ($pencairan as $item) {
                $rows[] = [
                    "type"              => "CASH_OUT",
                    "no_invoice"        => "",
                    "no_kontrak"        => $item->LOAN_NUM,
                    "tgl"               => date('Y-m-d', strtotime($item->ENTRY_DATE)),
                    "cabang"            => $item->BRANCH_NAME ?? "",
                    "user"              => $item->fullname ?? "",
                    "position"          => $item->position,
                    "nama_pelanggan"    => $item->CUSTOMER_NAME ?? "",
                    "metode_pembayaran" => "",
                    "keterangan"        => "PENCAIRAN " . strtoupper($item->CREDIT_TYPE) . " NO KONTRAK " . $item->LOAN_NUM,
                    "amount"            => (float) ($item->ORIGINAL_AMOUNT - $item->TOTAL_ADMIN)
                ];
            }

            $rows = array_values($rows);

            // Sort
            usort($rows, function ($a, $b) {
                return [
                    $a["tgl"],
                    $a["position"],
                    $a["no_kontrak"],
                    $a["no_invoice"]
                ] <=> [
                    $b["tgl"],
                    $b["position"],
                    $b["no_kontrak"],
                    $b["no_invoice"]
                ];
            });

            $grouped = [
                "TUNAI"      => ["title" => "UANG MASUK ( TUNAI )",             "data" => [], "jumlah" => 0, "colspan" => 9],
                "FEE_BUNGA"  => ["title" => "UANG MASUK ( FEE BUNGA MENURUN )", "data" => [], "jumlah" => 0, "colspan" => 9],
                "PELUNASAN"  => ["title" => "UANG MASUK ( PELUNASAN )",         "data" => [], "jumlah" => 0, "colspan" => 9],
                "TRANSFER"   => ["title" => "UANG MASUK ( TRANSFER )",          "data" => [], "jumlah" => 0, "colspan" => 9],
                "CASH_OUT"   => ["title" => "UANG KELUAR ( PENCAIRAN )",        "data" => [], "jumlah" => 0, "colspan" => 9],
            ];

            foreach ($rows as $row) {
                $nominal = $row["amount"];
                $isTransfer = strtolower($row["metode_pembayaran"]) === "transfer";

                if ($isTransfer && $row["type"] !== "CASH_OUT") {
                    $grouped["TRANSFER"]["data"][]  = $row;
                    $grouped["TRANSFER"]["jumlah"] += $nominal;
                    continue;
                }

                switch ($row["type"]) {
                    case "CASH_OUT":
                        $grouped["CASH_OUT"]["data"][]   = $row;
                        $grouped["CASH_OUT"]["jumlah"]  += $nominal;
                        break;

                    case "PELUNASAN":
                        $nominal = round($row["amount"]);
                        $grouped["PELUNASAN"]["data"][]  = $row;
                        $grouped["PELUNASAN"]["jumlah"] += $nominal;
                        break;

                    case "FEE_BUNGA":
                        $grouped["FEE_BUNGA"]["data"][]  = $row;
                        $grouped["FEE_BUNGA"]["jumlah"] += $nominal;
                        break;

                    case "CASH_IN":
                        if (strtolower($row["metode_pembayaran"]) === "cash") {
                            $grouped["TUNAI"]["data"][]   = $row;
                            $grouped["TUNAI"]["jumlah"]  += $nominal;
                        } else {
                            $grouped["TRANSFER"]["data"][]  = $row;
                            $grouped["TRANSFER"]["jumlah"] += $nominal;
                        }
                        break;
                }
            }

            // Format output akhir
            foreach ($grouped as &$g) {
                if (fmod($g["jumlah"], 1) == 0) {
                    $g["jumlah"] = "Rp." . number_format($g["jumlah"], 0, ',', '.');
                } else {
                    $g["jumlah"] = "Rp." . number_format($g["jumlah"], 2, ',', '.');
                }

                foreach ($g["data"] as &$row) {

                    if (fmod($row["amount"], 1) == 0) {
                        $amountFormatted = number_format($row["amount"], 0, ',', '.');
                    } else {
                        $amountFormatted = number_format($row["amount"], 2, ',', '.');
                    }

                    $row = [
                        $row["cabang"],
                        $row["tgl"],
                        $row["user"],
                        $row["position"],
                        $row["no_kontrak"],
                        $row["nama_pelanggan"],
                        $row["keterangan"],
                        $row["metode_pembayaran"],
                        $amountFormatted
                    ];
                }

                unset($row);
            }
            unset($g);

            $result["Result"] = array_values($grouped);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function VisitReports(Request $request)
    {
        try {
            $cabangId = $request->cabang_id;
            $no_kontrak = $request->no_kontrak;
            $nama = $request->nama;
            $dari     = $request->dari ?? now()->toDateString();
            $sampai   = $request->sampai ?? now()->toDateString();

            $results = DB::table('cl_deploy as a')
                ->select(
                    'a.LOAN_NUMBER',
                    'e.CREATED_AT',
                    'e.PATH',
                    'e.CONFIRM_DATE',
                    'e.DESCRIPTION',
                    'f.fullname',
                    'g.NAME',
                    'g.INS_ADDRESS',
                    'g.PHONE_PERSONAL',
                    'd.category',
                    'h.REF_PELANGGAN',
                    'h.REF_PELANGGAN_OTHER',
                    'i.NAME as Cabang'
                )
                ->leftJoin('credit as b', 'b.ID', '=', 'a.CREDIT_ID')
                ->leftJoin('cr_application as c', 'c.ORDER_NUMBER', '=', 'b.ORDER_NUMBER')
                ->leftJoin('cr_survey as d', 'd.id', '=', 'c.CR_SURVEY_ID')
                ->join('cl_survey_logs as e', 'e.REFERENCE_ID', '=', 'a.NO_SURAT')
                ->leftJoin('users as f', 'f.id', '=', 'e.CREATED_BY')
                ->leftJoin('customer as g', 'g.CUST_CODE', '=', 'b.CUST_CODE')
                ->leftJoin('cr_order as h', 'h.APPLICATION_ID', '=', 'c.ID')
                ->leftJoin('branch as i', 'i.ID', '=', 'a.BRANCH_ID')
                ->when($cabangId && $cabangId !== 'SEMUA CABANG', function ($query) use ($cabangId) {
                    $query->where('a.BRANCH_ID', $cabangId);
                })
                ->when($no_kontrak, function ($query) use ($no_kontrak) {
                    $query->where('a.LOAN_NUMBER', 'LIKE', "%$no_kontrak%");
                })

                ->when($nama, function ($query) use ($nama) {
                    $query->where('g.NAME', 'LIKE', "%$nama%");
                })
                ->whereBetween(DB::raw('DATE(e.CREATED_AT)'), [$dari, $sampai])
                ->orderByDesc('e.CREATED_AT')
                ->get();

            $dto = R_VisitReports::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function FasilitasLunasReport(Request $request)
    {
        try {
            $now = Carbon::now('Asia/Jakarta');
            $getNow = $now->format('mY');

            $cabangId = $request->cabang_id;
            $dari     = $request->dari ?? now()->toDateString();
            $sampai   = $request->sampai ?? now()->toDateString();
            $dateFrom = $getNow;

            $query = "SELECT	CONCAT(b.CODE, '-', b.CODE_NUMBER) AS KODE,
                                b.NAME AS NAMA_CABANG,
                                cl.LOAN_NUMBER AS NO_KONTRAK,
                                c.NAME AS NAMA_PELANGGAN,
                                cl.CREATED_AT AS TGL_BOOKING,
                                NULL AS UB,
                                NULL AS PLATFORM,
                                CONCAT(c.INS_ADDRESS,' RT/', c.INS_RT, ' RW/', c.INS_RW, ' ', c.INS_CITY, ' ', c.INS_PROVINCE) AS ALAMAT_TAGIH,
                                c.INS_KECAMATAN AS KODE_POST,
                                c.INS_KELURAHAN AS SUB_ZIP,
                                c.PHONE_HOUSE AS NO_TELP,
                                c.PHONE_PERSONAL AS NO_HP,
                                c.PHONE_PERSONAL AS NO_HP2,
                                c.OCCUPATION AS PEKERJAAN,
                                -- CONCAT(co.REF_PELANGGAN, ' ', co.REF_PELANGGAN_OTHER) AS supplier,
                                CONCAT(
                                    COALESCE(co.REF_PELANGGAN, ''),
                                    ' ',
                                    COALESCE(co.REF_PELANGGAN_OTHER, '')
                                ) AS supplier,
                                coalesce(u.fullname,cl.mcf_id) AS SURVEYOR,
                                -- cs.survey_note AS CATT_SURVEY,
                                coalesce(cs.survey_note,osn.SURVEY_NOTE) AS CATT_SURVEY,
                                replace(format(cl.PCPL_ORI ,0),',','') AS PKK_HUTANG,
                                cl.PERIOD AS JUMLAH_ANGSURAN,
                                replace(format(cl.PERIOD/cl.INSTALLMENT_COUNT,0),',','') AS JARAK_ANGSURAN,
                                cl.INSTALLMENT_COUNT as PERIOD,
                                replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.PCPL_ORI
			 			                        else st.init_pcpl end,0),',','') AS OUTSTANDING,
		                        replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then cl.INTRST_ORI
			 			                        else st.init_int end,0),',','') AS OS_BUNGA,
                                case when coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) < 0 then 0
                                    else coalesce(datediff(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end),0) end as OVERDUE_AWAL,
                                replace(format(coalesce(st.arr_pcpl,0),0),',','') as AMBC_PKK_AWAL,
                                replace(format(coalesce(st.arr_int,0),0),',','') as AMBC_BNG_AWAL,
                                replace(format((coalesce(st.arr_pcpl,0)+coalesce(st.arr_int,0)),0),',','') as AMBC_TOTAL_AWAL,
                                concat('C',case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 'N'
                                                when cl.STATUS_REC = 'RP' and py.ID is null and date_format(col.SITA_AT,'%m%Y')<>'$dateFrom'  then 'L'
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (st.init_pcpl+st.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(st.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),'%m%Y') then 'N'
                                                when st.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month) then 'N'
                                                when st.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when st.arr_count > 8 then 'X'
                                                else st.arr_count end) AS CYCLE_AWAL,
                                cl.STATUS_REC,
                                kjg.kunj_terakhir as STATUS_BEBAN,
                                -- case when (cl.PERIOD/cl.INSTALLMENT_COUNT)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar,
                                replace(format(coalesce(en.init_pcpl,0),0),',','') OS_PKK_AKHIR,
                                replace(format(coalesce(en.init_int,0),0),',','') as OS_BNG_AKHIR,
                                case when coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) < 0 then 0
                                    else coalesce(datediff(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),en.first_arr),0) end as OVERDUE_AKHIR,
                                cl.INSTALLMENT,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then 1
                                			 when coalesce(st.first_arr,en.first_arr) is null then ''
                                			 else coalesce(st.last_inst,en.last_inst) end as LAST_INST,
                                ca.INSTALLMENT_TYPE AS tipe,
                                case when date_format(cl.created_at,'%m%Y')='$dateFrom' then en.first_installment else coalesce(st.first_arr,en.first_arr) end as F_ARR_CR_SCHEDL,
                                en.first_arr as curr_arr,
                                py.last_pay as LAST_PAY,
                                k.kolektor AS COLLECTOR,
                                py.payment_method as cara_bayar,
                                replace(format(coalesce(en.arr_pcpl,0),0),',','') as AMBC_PKK_AKHIR,
                                replace(format(coalesce(en.arr_int ,0),0),',','') as AMBC_BNG_AKHIR,
                                replace(format(coalesce(en.arr_pcpl,0)+coalesce(en.arr_int,0),0),',','') as AMBC_TOTAL_AKHIR,
                                replace(format(coalesce(py.this_pcpl,0),0),',','') AC_PKK,
                                replace(format(coalesce(py.this_int,0),0),',','') AC_BNG_MRG,
                                replace(format(coalesce(py.this_cash,0),0),',','') AC_TOTAL,
                                concat('C',case when cl.STATUS <> 'A' then 'L'
                                                when replace(format(case when date_format(cl.created_at,'%m%Y')='$dateFrom' then (cl.PCPL_ORI+cl.INTRST_ORI)
			 			                                            else (en.init_pcpl+en.init_int) end,0),',','')=0 then 'L'
                                                when case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'MUSIMAN'
                                                        and date_format(en.first_arr,'%m%Y')=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),'%m%Y') then 'N'
                                                when en.first_arr>=date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 3 month) then 'N'
                                                when en.first_arr > date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 2 month),interval -1 day)
                                                        and case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end = 'REGULER'  then 'M'
                                                when en.arr_count > 8 then 'X'
                                                else en.arr_count end) AS CYCLE_AKHIR,
                                -- case when (cl.INSTALLMENT_COUNT/cl.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar_akhir,
                                case when cl.CREDIT_TYPE = 'bulanan' then 'reguler' else cl.CREDIT_TYPE end as pola_bayar_akhir,
                                col.COL_TYPE as jenis_jaminan,
                                col.COLLATERAL,
                                col.POLICE_NUMBER,
                                col.ENGINE_NUMBER,
                                col.CHASIS_NUMBER,
                                col.PRODUCTION_YEAR,
                                replace(format(cl.PCPL_ORI-cl.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                                replace(format(cl.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                                cl.CUST_CODE
                        FROM	credit cl
                                inner join branch b on cast(b.ID as char) = cast(cl.BRANCH as char)
                                left join customer c on cast(c.CUST_CODE as char) = cast(cl.CUST_CODE as char)
                                left join users u on cast(u.ID as char) = cast(cl.MCF_ID as char)
                                left join cr_application ca on cast(ca.ORDER_NUMBER as char) = cast(cl.ORDER_NUMBER as char)
                                left join cr_order co on cast(co.APPLICATION_ID as char) = cast(ca.ID as char)
                                left join cr_survey cs on cast(cs.ID as char) = cast(ca.CR_SURVEY_ID as char)
                                left join kolektor k on cast(k.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                left join temp_lis_03C col on cast(col.CR_CREDIT_ID as char) = cast(cl.ID as char)
                                left join temp_lis_01C st
                                    on cast(st.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and st.type=date_format(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),'%d%m%Y')
                                left join temp_lis_01C en
                                    on cast(en.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                    and en.type=date_format(now(),'%d%m%Y')
                                left join temp_lis_02C py on cast(py.loan_num as char) = cast(cl.LOAN_NUMBER as char)
                                left join old_survey_note osn on cast(osn.loan_number as char) = cast(cl.LOAN_NUMBER as char)
                                
                                left join ( select	cd.CREDIT_ID, group_concat(distinct csl.DESCRIPTION) as kunj_terakhir
                                            from	cl_survey_logs csl 
                                                    inner join cl_deploy cd on cd.NO_SURAT = csl.REFERENCE_ID
                                            where	(cd.CREDIT_ID, csl.CREATED_AT) in
                                                        (	select	cd.CREDIT_ID, max(csl.CREATED_AT)
                                                            from	cl_survey_logs csl 
                                                                    inner join cl_deploy cd on cd.NO_SURAT = csl.REFERENCE_ID
                                                            group	by cd.CREDIT_ID )
                                            group	by cd.CREDIT_ID) kjg on kjg.CREDIT_ID = cl.ID
                                
                        WHERE	(cl.STATUS = 'A'  
                                    or (cl.STATUS_REC = 'RP' and coalesce(cl.mod_user,'') <> 'exclude jaminan' and cast(cl.LOAN_NUMBER as char) not in (select cast(pp.LOAN_NUM as char) from payment pp where pp.ACC_KEY = 'JUAL UNIT'))
                                    or (cast(cl.LOAN_NUMBER as char) in (select cast(loan_num as char) from temp_lis_02C )))";

                $checkRunSp = DB::select("  SELECT
                                            CASE
                                                WHEN (SELECT MAX(p.ENTRY_DATE) FROM payment p) >= (SELECT coalesce(MAX(temp_lis_02C.last_pay),(SELECT MAX(p.ENTRY_DATE) FROM payment p)) FROM temp_lis_02C)
                                                    AND job_status = 0 THEN 'run'
                                                ELSE 'skip'
                                            END AS execute_sp
                                            FROM job_on_progress
                                            WHERE job_name = 'LISBAN'");

                $query .= " ORDER BY b.NAME,cl.CREATED_AT ASC";

                if (!empty($checkRunSp) && $checkRunSp[0]->execute_sp === 'run') {
                    DB::select('CALL lisban_berjalan(?,?,?)', [Carbon::now('Asia/Jakarta')->format('mY'), $request->user()->fullname, 'semua']);
                }

                $results = DB::select($query);
            DB::select("UPDATE job_on_progress SET job_status = 0, last_user='' WHERE job_name = ?", ["LISBAN"]);

            $build = [];
            foreach ($results as $result) {

                $getUsers = User::find($result->SURVEYOR);

                $cleanDate = trim($result->LAST_PAY);
                $cleanDate = preg_replace('/[^\d\/\-\.]/', '', $cleanDate);

                $build[] = [
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" => is_numeric($result->NO_KONTRAK) ? (int) $result->NO_KONTRAK ?? '' : $result->NO_KONTRAK ?? '',
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ?  Carbon::parse($result->TGL_BOOKING)->format('m/d/Y') : '',
                    "UB" => $result->UB ?? '',
                    "PLATFORM" => $result->PLATFORM ?? '',
                    "ALAMAT TAGIH" => $result->ALAMAT_TAGIH ?? '',
                    "KECAMATAN" => $result->KODE_POST ?? '',
                    "KELURAHAN" => $result->SUB_ZIP ?? '',
                    "NO TELP" => $result->NO_TELP ?? '',
                    "NO HP1" => $result->NO_HP ?? '',
                    "NO HP2" => $result->NO_HP2 ?? '',
                    "PEKERJAAN" => $result->PEKERJAAN ?? '',
                    "SUPPLIER" => $result->supplier ?? '',
                    "SURVEYOR" => $getUsers ? $getUsers->fullname ?? '' : $result->SURVEYOR ?? '',
                    "CATT SURVEY" => $result->CATT_SURVEY ?? '',
                    "PKK HUTANG" => (int) $result->PKK_HUTANG ?? 0,
                    "JML ANGS" => $result->JUMLAH_ANGSURAN ?? '',
                    "JRK ANGS" => (int) $result->JARAK_ANGSURAN ?? '',
                    "PERIOD" => $result->PERIOD ?? '',
                    "OUT PKK AWAL" => (int) $result->OUTSTANDING ?? 0,
                    "OUT BNG AWAL" => (int) $result->OS_BUNGA ?? 0,
                    "OVERDUE AWAL" => $result->OVERDUE_AWAL ?? 0,
                    "AMBC PKK AWAL" => (int) $result->AMBC_PKK_AWAL,
                    "AMBC BNG AWAL" => (int) $result->AMBC_BNG_AWAL,
                    "AMBC TOTAL AWAL" => (int) $result->AMBC_TOTAL_AWAL,
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "KUNJUNGAN TERAKHIR" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => '',
                    "OUTS PKK AKHIR" => (int) $result->OS_PKK_AKHIR ?? 0,
                    "OUTS BNG AKHIR" => (int) $result->OS_BNG_AKHIR ?? 0,
                    "OVERDUE AKHIR" => (int) $result->OVERDUE_AKHIR ?? 0,
                    "ANGSURAN" => (int) $result->INSTALLMENT ?? 0,
                    "ANGS KE" => (int) $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->pola_bayar === 'bunga_menurun' ? str_replace('_', ' ', $result->pola_bayar) : $result->pola_bayar ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' :  Carbon::parse($result->F_ARR_CR_SCHEDL)->format('m/d/Y'),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : Carbon::parse($result->curr_arr)->format('m/d/Y'),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : Carbon::parse($cleanDate)->format('m/d/Y'),
                    "KOLEKTOR" => $result->COLLECTOR,
                    "CARA BYR" => $result->cara_bayar,
                    "AMBC PKK_AKHIR" => (int) $result->AMBC_PKK_AKHIR ?? 0,
                    "AMBC BNG_AKHIR" => (int) $result->AMBC_BNG_AKHIR ?? 0,
                    "AMBC TOTAL_AKHIR" => (int) $result->AMBC_TOTAL_AKHIR ?? 0,
                    "AC PKK" => (int) $result->AC_PKK,
                    "AC BNG MRG" => (int) $result->AC_BNG_MRG,
                    "AC TOTAL" => (int) $result->AC_TOTAL,
                    "CYCLE AKHIR" => $result->CYCLE_AKHIR,
                    "POLA BYR AKHIR" => '',
                    "NAMA BRG" => $result->jenis_jaminan,
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" => (int) $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => (int) $result->NILAI_PINJAMAN ?? 0,
                    "ADMIN" => (int) $result->TOTAL_ADMIN ?? '',
                    "CUST_ID" => is_numeric($result->CUST_CODE) ? (int) $result->CUST_CODE ?? '' : $result->CUST_CODE ?? ''
                ];
            }

            return response()->json($build, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}

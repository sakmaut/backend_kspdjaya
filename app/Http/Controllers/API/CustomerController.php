<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
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
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {

            $search = $request->get('search');

            if (isset($search)) {
                $customers = M_Customer::where('CUST_CODE', 'LIKE', "%{$search}%")
                    ->orWhere(DB::raw("CONCAT(NAME, ' ', ALIAS)"), 'LIKE', "%{$search}%")
                    ->orWhere('MOTHER_NAME', 'LIKE', "%{$search}%")
                    ->paginate(10);
            } else {
                $customers = M_Customer::paginate(10);
            }

            $customers->getCollection()->transform(function ($customer) {
                $credit = M_Credit::where('CUST_CODE', $customer->CUST_CODE)->first();

                if (!empty($credit->ID)) {
                    $customer->jaminan = M_CrCollateral::where('CR_CREDIT_ID', $credit->ID)->first();
                }

                return $customer;
            });

            return response()->json($customers, 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            $findCustomerById = M_Customer::find($request->id);

            if (!$findCustomerById) {
                throw new Exception("Customer Id Not Found", 404);
            }

            $data = [
                'NAME' => $request->nama,
                'ALIAS' => $request->nama_panggilan,
                // 'GENDER' => $request->pelanggan['jenis_kelamin'],
                // 'BIRTHPLACE' => $request->pelanggan['tempat_lahir'],
                // 'BIRTHDATE' => date('Y-m-d', strtotime($request->pelanggan['tgl_lahir'])),
                // 'BLOOD_TYPE' => $request->pelanggan['gol_darah'],
                // 'MOTHER_NAME' => $request->pelanggan['ibu_kandung'],
                // 'MARTIAL_STATUS' => $request->pelanggan['status_kawin'],
                // 'MARTIAL_DATE' => date('Y-m-d', strtotime($request->pelanggan['tgl_kawin'])),
                // 'ID_TYPE' => $request->pelanggan['tipe_identitas'],
                // 'ID_NUMBER' => $request->pelanggan['no_identitas'],
                // 'KK_NUMBER' => $request->pelanggan['no_kk'],
                // 'ID_ISSUE_DATE' => date('Y-m-d', strtotime($request->pelanggan['tgl_terbit_identitas'])),
                // 'ID_VALID_DATE' => date('Y-m-d', strtotime($request->pelanggan['masa_berlaku_identitas'])),
                // 'KK' => $request->pelanggan['no_kk'],
                // 'CITIZEN' => $request->pelanggan['warganegara'],
                // 'ADDRESS' => $request->alamat_identitas['alamat'],
                // 'RT' => $request->alamat_identitas['rt'],
                // 'RW' => $request->alamat_identitas['rw'],
                // 'PROVINCE' => $request->alamat_identitas['provinsi'],
                // 'CITY' => $request->alamat_identitas['kota'],
                // 'KELURAHAN' => $request->alamat_identitas['kelurahan'],
                // 'KECAMATAN' => $request->alamat_identitas['kecamatan'],
                // 'ZIP_CODE' => $request->alamat_identitas['kode_pos'],
                // 'INS_ADDRESS' => $request->alamat_tagih['alamat'],
                // 'INS_RT' => $request->alamat_tagih['rt'],
                // 'INS_RW' => $request->alamat_tagih['rw'],
                // 'INS_PROVINCE' => $request->alamat_tagih['provinsi'],
                // 'INS_CITY' => $request->alamat_tagih['kota'],
                // 'INS_KELURAHAN' => $request->alamat_tagih['kelurahan'],
                // 'INS_KECAMATAN' => $request->alamat_tagih['kecamatan'],
                // 'INS_ZIP_CODE' => $request->alamat_tagih['kode_pos'],
                // 'OCCUPATION' => $request->pekerjaan['pekerjaan'],
                // 'OCCUPATION_ON_ID' => $request->pekerjaan['pekerjaan_id'],
                // 'INCOME' => $request->pekerjaan['nama'],
                // 'RELIGION' => $request->pekerjaan['agama'],
                // 'EDUCATION' => $request->pekerjaan['pendidikan'],
                // 'PROPERTY_STATUS' => $request->pekerjaan['status_rumah'],
                // 'PHONE_HOUSE' => $request->pekerjaan['telepon_rumah'],
                // 'PHONE_PERSONAL' => $request->pekerjaan['telepon_selular'],
                // 'PHONE_OFFICE' => $request->pekerjaan['telepon_kantor'],
                // 'EXT_1' => $request->pekerjaan['ekstra1'],
                // 'EXT_2' => $request->pekerjaan['ekstra2'],
                'MOD_DATE' => Carbon::now()->format('Y-m-d') ?? null,
                'MOD_USER' => $request->user()->id ?? ''
            ];

            $findCustomerById->update($data);

            return response()->json('Updated Success', 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
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
                ->where('a.STATUS', 'A');

            if (!empty($request->nama)) {
                $query->when($request->nama, function ($query, $nama) {
                    return $query->where("c.NAME", 'LIKE', "%{$nama}%");
                });
            }

            if (!empty($request->no_kontrak)) {
                $query->when($request->no_kontrak, function ($query, $no_kontrak) {
                    return $query->where('a.LOAN_NUMBER', 'LIKE', "%{$no_kontrak}%");
                });
            }

            if (!empty($request->no_polisi)) {
                $query->when($request->no_polisi, function ($query, $no_polisi) {
                    return $query->where('b.POLICE_NUMBER', 'LIKE', "%{$no_polisi}%");
                });
            }

            $results = $query->get();

            $dto = R_CustomerSearch::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function fasilitas(Request $request)
    {
        try {
            $data =  M_Credit::where(['CUST_CODE' => $request->cust_code, 'STATUS' => 'A'])->get();

            if ($data->isEmpty()) {
                throw new Exception("Cust Code Is Not Exist");
            }

            $dto = R_CreditList::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function creditStruktur(Request $request)
    {
        try {
            $schedule = [];

            $loanNumber = $request->loan_number;

            $data = DB::table('credit_schedule AS a')
                ->leftJoin('arrears AS b', function ($join) {
                    $join->on('b.LOAN_NUMBER', '=', 'a.LOAN_NUMBER')
                        ->on('b.START_DATE', '=', 'a.PAYMENT_DATE');
                })
                ->where('a.LOAN_NUMBER', $loanNumber)
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('a.PAID_FLAG', '!=', 'PAID')
                            ->orWhereNull('a.PAID_FLAG');
                    })
                        ->orWhereNotIn('b.STATUS_REC', ['S', 'D']);
                })
                ->orderBy('a.PAYMENT_DATE', 'ASC')
                ->select(
                    'a.LOAN_NUMBER',
                    'a.INSTALLMENT_COUNT',
                    'a.PAYMENT_DATE',
                    'a.PRINCIPAL',
                    'a.INTEREST',
                    'a.INSTALLMENT',
                    'a.PRINCIPAL_REMAINS',
                    'a.INSUFFICIENT_PAYMENT',
                    'a.PAYMENT_VALUE_PRINCIPAL',
                    'a.PAYMENT_VALUE_INTEREST',
                    'a.PAYMENT_VALUE',
                    'a.PAID_FLAG',
                    'b.STATUS_REC',
                    'b.ID as id_arrear',
                    'b.PAST_DUE_PENALTY',
                    'b.PAID_PENALTY'
                )
                ->get();


            if ($data->isEmpty()) {
                return $schedule;
            }

            $getCustomer = M_Credit::where('LOAN_NUMBER', $loanNumber)
                ->with(['customer' => function ($query) {
                    $query->select(
                        'CUST_CODE',
                        'NAME',
                        'ADDRESS',
                        'RT',
                        'RW',
                        'PROVINCE',
                        'CITY',
                        'KELURAHAN',
                        'KECAMATAN'
                    );
                }])
                ->first()
                ->customer;


            $j = 0;
            foreach ($data as $res) {

                $installment = floatval($res->INSTALLMENT) - floatval($res->PAYMENT_VALUE);

                if (!empty($res->STATUS_REC) && $res->STATUS_REC == 'PENDING') {
                    $cekStatus = $res->STATUS_REC;
                } else {
                    $cekStatus = $res->PAID_FLAG;
                }

                if ($res->PAID_FLAG == 'PAID' && ($res->STATUS_REC == 'D' || $res->STATUS_REC == 'S')) {
                    $cekStatus = 'PAID';
                }

                $schedule[] = [
                    'key' => $j++,
                    'angsuran_ke' => $res->INSTALLMENT_COUNT,
                    'loan_number' => $res->LOAN_NUMBER,
                    'tgl_angsuran' => Carbon::parse($res->PAYMENT_DATE)->format('d-m-Y'),
                    'principal' => floatval($res->PRINCIPAL),
                    'interest' => floatval($res->INTEREST),
                    'installment' => $installment,
                    'principal_prev' => floatval($res->PAYMENT_VALUE_PRINCIPAL),
                    'interest_prev' => floatval($res->PAYMENT_VALUE_INTEREST),
                    'insuficient_payment_prev' => floatval($res->INSUFFICIENT_PAYMENT),
                    'principal_remains' => floatval($res->PRINCIPAL_REMAINS),
                    'payment' => floatval($res->PAYMENT_VALUE),
                    'bayar_angsuran' => floatval($res->INSTALLMENT) - floatval($res->PAYMENT_VALUE),
                    'bayar_denda' => $installment == 0 ? 0 : floatval($res->PAST_DUE_PENALTY ?? 0) - floatval($res->PAID_PENALTY ?? 0),
                    'total_bayar' => floatval($res->INSTALLMENT + ($res->PAST_DUE_PENALTY ?? 0)),
                    'id_arrear' => $res->id_arrear ?? '',
                    'flag' => $res->PAID_FLAG ?? '',
                    'denda' => floatval($res->PAST_DUE_PENALTY ?? 0) - floatval($res->PAID_PENALTY ?? 0)
                ];
            }

            return response()->json($schedule, 200);
        } catch (Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function cekRO(Request $request)
    {
        try {
            $data = M_Customer::where('ID_NUMBER', $request->no_ktp)->get();

            $datas = $data->map(function ($customer) {

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
                                "document" => $this->getCollateralDocument($guarantee->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']),
                            ]
                        ];
                    }
                }


                foreach ($guarente_sertificat as $list) {
                    if (!empty($list->ID)) {
                        $jaminan[] = [
                            "type" => "sertifikat",
                            'counter_id' => $list->HEADER_ID ?? null,
                            "atr" => [
                                'id' => $list->ID ?? null,
                                'status_jaminan' => null,
                                "no_sertifikat" => $list->NO_SERTIFIKAT ?? null,
                                "status_kepemilikan" => $list->STATUS_KEPEMILIKAN ?? null,
                                "imb" => $list->IMB ?? null,
                                "luas_tanah" => $list->LUAS_TANAH ?? null,
                                "luas_bangunan" => $list->LUAS_BANGUNAN ?? null,
                                "lokasi" => $list->LOKASI ?? null,
                                "provinsi" => $list->PROVINSI ?? null,
                                "kab_kota" => $list->KAB_KOTA ?? null,
                                "kec" => $list->KECAMATAN ?? null,
                                "desa" => $list->DESA ?? null,
                                "atas_nama" => $list->ATAS_NAMA ?? null,
                                "nilai" => (int)$list->NILAI ?? null,
                                "document" => $this->getCollateralDocument($guarantee->ID, ['sertifikat'])
                            ]
                        ];
                    }
                }

                return [
                    'no_ktp' => $customer->ID_NUMBER ?? null,
                    'no_kk' => $customer->KK_NUMBER ?? null,
                    'nama' => $customer->NAME ?? null,
                    'tgl_lahir' => $customer->BIRTHDATE ?? null,
                    'alamat' => $customer->ADDRESS ?? null,
                    'rw' => $customer->RW ?? null,
                    'rt' => $customer->RT ?? null,
                    'provinsi' => $customer->PROVINCE ?? null,
                    'city' => $customer->CITY ?? null,
                    'kecamatan' => $customer->KECAMATAN ?? null,
                    'kelurahan' => $customer->KELURAHAN ?? null,
                    'kode_pos' => $customer->ZIP_CODE ?? null,
                    'no_hp' => $customer->PHONE_PERSONAL ?? null,
                    "dokumen_indentitas" => M_CustomerDocument::where('CUSTOMER_ID', $customer->ID)->get(),
                    'jaminan' => $jaminan
                ];
            })->toArray();

            return response()->json($datas, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCollateralDocument($creditID, $param)
    {
        $documents = DB::table('cr_collateral_document')
            ->whereIn('TYPE', $param)
            ->where('COLLATERAL_ID', '=', $creditID)
            ->get();

        return $documents;
    }
}

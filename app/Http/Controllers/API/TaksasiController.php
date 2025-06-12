<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_Taksasi;
use App\Models\M_Taksasi;
use App\Models\M_TaksasiBak;
use App\Models\M_TaksasiPrice;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class TaksasiController extends Controller
{

    protected $log;
    private $timeNow;

    public function __construct(ExceptionHandling $log)
    {
        $this->timeNow = Carbon::now();
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $data = M_Taksasi::whereNull('deleted_by')->get();
            $dto = R_Taksasi::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function brandList(Request $request)
    {
        try {
            $data = M_Taksasi::distinct()
                ->select('brand')
                ->get()
                ->pluck('brand')
                ->toArray();

            $result = ['brand' => $data];

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function codeModelList(Request $request)
    {
        try {

            if (empty($request->merk)) {
                return response()->json([], 200);
            }

            $data = M_Taksasi::select('id', 'code', DB::raw("CONCAT(model, ' - ', descr) AS model"))
                ->where('brand', $request->merk)
                ->distinct()
                ->get()
                ->toArray();

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function year(Request $request)
    {
        try {
            if (empty($request->merk) || empty($request->tipe)) {
                return response()->json([], 200);
            }

            $data = M_Taksasi::select('id')
                ->where('brand', '=', $request->merk)
                ->whereRaw('CONCAT(code, " - ", model, " - ", descr) = ?', [$request->tipe])
                ->first();

            $year = M_TaksasiPrice::distinct()
                ->select('year')
                ->where('taksasi_id', '=', $data->id)
                ->orderBy('year', 'asc')
                ->get()
                ->toArray();

            $years = array_column($year, 'year');

            return response()->json($years, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function price(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required',
                'tahun' => 'required',
            ], [
                'code.required' => 'Code Tidak Boleh Kosong',
                'tahun.required' => 'Tahun Tidak Boleh Kosong'
            ]);

            $tipe_array = explode(' - ', $request->code);

            $data = M_Taksasi::select(
                'taksasi.code',
                'taksasi_price.year',
                DB::raw('CAST(taksasi_price.price AS UNSIGNED) AS price')
            )
                ->join('taksasi_price', 'taksasi_price.taksasi_id', '=', 'taksasi.id')
                ->where('taksasi.code', '=', $tipe_array[0])
                ->where('taksasi.model', '=', $tipe_array[1])
                ->where('taksasi_price.year', '=',  $request->tahun)
                ->get();
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = M_Taksasi::where('id', $id)->first();

            if (!$data) {
                throw new Exception("Data Not Found", 1);
            }

            $dto = new R_Taksasi($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            if (empty($request->merk) || empty($request->tipe) || empty($request->keterangan) || empty($request->jenis)) {
                throw new Exception("Data kendaraan tidak lengkap. Pastikan Merk, Tipe, Jenis,Model, dan Keterangan terisi.");
            }

            $data_taksasi = [
                'vehicle_type' => strtoupper($request->jenis ?? ''),
                'brand' => strtoupper($request->merk),
                'code' => strtoupper($request->tipe),
                'model' => strtoupper($request->model),
                'descr' => strtoupper($request->keterangan),
                'create_by' => $request->user()->id,
                'create_at' => $this->timeNow
            ];

            $taksasi_id = M_Taksasi::create($data_taksasi);

            if (isset($request->price) && is_array($request->price)) {
                foreach ($request->price as $res) {
                    $taksasi_price = [
                        'taksasi_id' => $taksasi_id->id,
                        'year' => $res['tahun'],
                        'price' => $res['harga']
                    ];

                    M_TaksasiPrice::create($taksasi_price);
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $taksasi = M_Taksasi::where('id', $id)->first();

            if (!$taksasi) {
                throw new Exception("Data Not Found", 1);
            }

            $data_taksasi = [
                'vehicle_type' => $request->jenis ?? '',
                'brand' => $request->merk ?? '',
                'code' => $request->tipe ?? '',
                'model' => $request->model ?? '',
                'descr' => $request->keterangan ?? '',
                'updated_by' => $request->user()->id,
                'updated_at' => $this->timeNow
            ];

            $taksasi->update($data_taksasi);

            $taksasi_price = M_TaksasiPrice::where('taksasi_id', $id)->delete();

            if (isset($request->price) && is_array($request->price)) {
                foreach ($request->price as $res) {
                    $taksasi_price = [
                        'taksasi_id' => $id,
                        'year' => $res['tahun'],
                        'price' => $res['harga']
                    ];

                    M_TaksasiPrice::create($taksasi_price);
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $taksasi = M_Taksasi::where('id', $id)->first();

            if (!$taksasi) {
                throw new Exception("Data Not Found", 1);
            }

            $update = [
                'deleted_by' => $request->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $taksasi->update($update);

            DB::commit();
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function updateAll(Request $request)
    {
        DB::beginTransaction();
        try {

            $getDataVehicle = collect($request->json()->all());

            if ($getDataVehicle->isEmpty()) {
                return response()->json(['error' => 'No taksasi data provided'], 400);
            }

            $insertData = [];
            $dataExist = [];

            foreach ($getDataVehicle as $vehicle) {
                $uuid = Uuid::uuid7()->toString();

                if (empty($vehicle['merk']) || empty($vehicle['tipe']) || empty($vehicle['keterangan'])) {
                    throw new Exception("Data kendaraan tidak lengkap. Pastikan 'merk', 'tipe', 'model', dan 'keterangan' terisi.");
                }

                $uniqueKey = $vehicle['merk'] . '-' . $vehicle['tipe'] . '-' . $vehicle['keterangan'];

                $formattedPrice = number_format(floatval(str_replace(',', '', $vehicle['harga'] ?? '0')), 0, '.', '');

                if (!isset($dataExist[$uniqueKey])) {
                    $insertData[] = [
                        'id' => $uuid,
                        'vehicle_type' => $vehicle['jenis'] ?? '',
                        'brand' => $vehicle['merk'] ?? '',
                        'code' => $vehicle['tipe'] ?? '',
                        'model' => $vehicle['tipe'] ?? '',
                        'descr' => $vehicle['keterangan'] ?? '',
                        'tahun' => [
                            [
                                'year' => $vehicle['tahun'] ?? '',
                                'price' => $formattedPrice
                            ]
                        ],
                        'create_by' => $request->user()->id,
                        'create_at' => now(),
                    ];

                    $dataExist[$uniqueKey] = count($insertData) - 1;
                } else {
                    $existingIndex = $dataExist[$uniqueKey];

                    $yearExists = false;
                    foreach ($insertData[$existingIndex]['tahun'] as $yearEntry) {
                        if ($yearEntry['year'] === $vehicle['tahun']) {
                            $yearExists = true;
                            break;
                        }
                    }

                    if (!$yearExists) {
                        $insertData[$existingIndex]['tahun'][] = [
                            'year' => $vehicle['tahun'] ?? '',
                            'price' => $formattedPrice
                        ];
                    }
                }
            }

            if (count($insertData) > 0) {

                M_Taksasi::query()->delete();
                M_TaksasiPrice::query()->delete();

                foreach ($insertData as $data) {
                    M_Taksasi::insert([
                        'id' => $data['id'],
                        'vehicle_type' => $data['vehicle_type'] ?? '',
                        'brand' => $data['brand'],
                        'code' => $data['code'],
                        'model' => $data['model'],
                        'descr' => $data['descr'],
                        'create_by' => $data['create_by'],
                        'create_at' => $data['create_at'],
                    ]);

                    foreach ($data['tahun'] as $yearData) {
                        M_TaksasiPrice::insert([
                            'id' => Uuid::uuid7()->toString(),
                            'taksasi_id' => $data['id'],
                            'year' => $yearData['year'] ?? '',
                            'price' => $yearData['price'] ?? 0
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'CIE TAKSASINYA SUKSES DIUPDATE'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function updateAllChangeData(Request $request)
    {
        DB::beginTransaction();
        try {
            $getDataVehicle = collect($request->json()->all());

            if ($getDataVehicle->isEmpty()) {
                return response()->json(['error' => 'No taksasi data provided'], 400);
            }

            $setUniqueKey = [];
            $vehicleMap = [];

            foreach ($getDataVehicle as $vehicle) {
                $key = $vehicle['brand'] . '-' . $vehicle['model'] . '-' . $vehicle['descr'];
                $formattedPrice = number_format(floatval(str_replace(',', '', $vehicle['price'] ?? '0')), 0, '.', '');

                if (!isset($vehicleMap[$key])) {

                    $setUniqueKey[] = $key;

                    $vehicleMap[$key] = [
                        'jenis' => $vehicle['jenis'],
                        'brand' => $vehicle['brand'],
                        'model' => $vehicle['model'],
                        'descr' => $vehicle['descr'],
                        'price' => $formattedPrice,
                        'years' => []
                    ];
                }

                $alreadyExists = false;
                foreach ($vehicleMap[$key]['years'] as $yearData) {
                    if ($yearData['year'] === $vehicle['year']) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (!$alreadyExists) {
                    $vehicleMap[$key]['years'][] = [
                        'year' => $vehicle['year'],
                        'price' => $formattedPrice
                    ];
                }
            }

            $checkTaksasiData = DB::table('taksasi as a')
                ->leftJoin('taksasi_price as b', 'b.taksasi_id', '=', 'a.id')
                ->select('a.id', 'a.vehicle_type', 'a.brand', 'a.model', 'a.descr', 'b.id as price_id', 'b.year', 'b.price', DB::raw("CONCAT(a.brand,'-',a.model,'-',a.descr) as keyss"))
                ->whereRaw("CONCAT(a.brand,'-',a.model,'-',a.descr) IN (" . implode(',', array_fill(0, count($setUniqueKey), '?')) . ")", $setUniqueKey)
                ->get();

            $dbDataMap = [];
            foreach ($checkTaksasiData as $data) {
                $compoundKey = $data->keyss . '-' . $data->year;
                $dbDataMap[$compoundKey] = $data;
            }

            foreach ($vehicleMap as $key => $vehicle) {
                foreach ($vehicle['years'] as $yearData) {
                    $compoundKey = $key . '-' . $yearData['year'];

                    if (isset($dbDataMap[$compoundKey])) {
                        M_TaksasiPrice::where('id', $dbDataMap[$compoundKey]->price_id)
                            ->update(['price' => floatval($yearData['price'])]);
                    } else {
                        $taksasiId = null;

                        $existingTaksasi = M_Taksasi::where([
                            ['vehicle_type', '=', strtoupper($vehicle['jenis'] ?? '')],
                            ['brand', '=', $vehicle['brand'] ?? ''],
                            ['code', '=', $vehicle['model'] ?? ''],
                            ['model', '=', $vehicle['model'] ?? ''],
                            ['descr', '=', $vehicle['descr'] ?? ''],
                        ])->first();

                        if ($existingTaksasi) {
                            $taksasiId = $existingTaksasi->id;
                        } else {
                            $taksasiId = Uuid::uuid7()->toString();
                            M_Taksasi::insert([
                                'id' => $taksasiId,
                                'vehicle_type' => strtoupper($vehicle['jenis'] ?? ''),
                                'brand' => $vehicle['brand'] ?? '',
                                'code' => $vehicle['model'] ?? '',
                                'model' => $vehicle['model'] ?? '',
                                'descr' => $vehicle['descr'] ?? '',
                                'create_by' => $request->user()->id,
                                'create_at' => now(),
                            ]);
                        }

                        // Insert harga tahun untuk kendaraan
                        M_TaksasiPrice::insert([
                            'id' => Uuid::uuid7()->toString(),
                            'taksasi_id' => $taksasiId,
                            'year' => $yearData['year'],
                            'price' => floatval($yearData['price']),
                        ]);
                    }
                }
            }


            DB::commit();
            return response()->json(['message' => 'anjay sukses'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function download(Request $request)
    {
        DB::beginTransaction();
        try {

            $result = DB::table('taksasi as a')
                ->leftJoin('taksasi_price as b', 'b.taksasi_id', '=', 'a.id')
                ->select('a.vehicle_type as jenis', 'a.brand as merk', 'a.code as tipe', 'a.model', 'a.descr as keterangan', 'b.year as tahun', DB::raw('CAST(b.price AS UNSIGNED) AS harga'))
                ->orderBy('a.brand')
                ->orderBy('a.code')
                ->orderBy('b.year', 'asc')
                ->get();

            DB::commit();
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}

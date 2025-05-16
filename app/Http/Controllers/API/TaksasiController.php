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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
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
            $data = M_Taksasi::all();
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
            $request->validate([
                'merk' => 'required',
            ], [
                'merk.required' => 'Merk Tidak Boleh Kosong',
            ]);

            $data = M_Taksasi::select('id', 'code', DB::raw("CONCAT(model, ' - ', descr) AS model"))
                ->where('brand', $request->merk)
                ->distinct()
                ->get()
                ->toArray();


            // $year = M_TaksasiPrice::distinct()
            //         ->select('year')
            //         ->orderBy('year','asc')
            //         ->get()
            //         ->pluck('year')
            //         ->toArray();

            // foreach ($data as &$item) {
            //     $item['tahun'] = $year;
            // }

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function year(Request $request)
    {
        try {
            $request->validate([
                'merk' => 'required',
                'tipe' => 'required',
            ], [
                'merk.required' => 'Merk Tidak Boleh Kosong',
                'tipe.required' => 'Tipe Tidak Boleh Kosong',
            ]);

            $tipe_array = explode(' - ', $request->tipe);

            // $data = M_Taksasi::distinct()
            //     ->select('id')
            //     ->where('brand', '=', $request->merk)
            //     ->where('code', '=', $tipe_array[0])
            //     ->where('model', '=', $tipe_array[1])
            //     ->get();

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

            $data_taksasi = [
                'vehicle_type' => strtoupper($request->jenis_kendaraan),
                'brand' => strtoupper($request->brand),
                'code' => strtoupper($request->code),
                'model' => strtoupper($request->model),
                'descr' => strtoupper($request->descr),
                'create_by' => $request->user()->id,
                'create_at' => $this->timeNow
            ];

            $taksasi_id = M_Taksasi::create($data_taksasi);

            if (isset($request->price) && is_array($request->price)) {
                foreach ($request->price as $res) {
                    $taksasi_price = [
                        'taksasi_id' => $taksasi_id->id,
                        'year' => $res['name'],
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
                'vehicle_type' => $request->jenis_kendaraan,
                'brand' => $request->brand,
                'code' => $request->code,
                'model' => $request->model,
                'descr' => $request->descr,
                'updated_by' => $request->user()->id,
                'updated_at' => $this->timeNow
            ];

            $taksasi->update($data_taksasi);

            $taksasi_price = M_TaksasiPrice::where('taksasi_id', $id)->delete();

            if (isset($request->price) && is_array($request->price)) {
                foreach ($request->price as $res) {
                    $taksasi_price = [
                        'taksasi_id' => $id,
                        'year' => $res['name'],
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

            $vehicles = collect($request->json()->all());

            if ($vehicles->isEmpty()) {
                return response()->json(['error' => 'No taksasi data provided'], 400);
            }

            $result = DB::table('taksasi as a')
                ->leftJoin('taksasi_price as b', 'b.taksasi_id', '=', 'a.id')
                ->select('a.brand', 'a.code', 'a.model', 'a.descr', 'b.year', 'b.price')
                ->orderBy('a.brand')
                ->orderBy('a.code')
                ->orderBy('b.year', 'asc')
                ->get();

            if ($result->isNotEmpty()) {

                $max = DB::table('taksasi_bak')
                    ->select(DB::raw('max(coalesce(count, 0)) as htung'))
                    ->first();

                $result->map(function ($list) use ($request, $max) {
                    $log = [
                        'count' => intval($max->htung ?? 0) + 1,
                        'brand' => $list->brand,
                        'code' => $list->code,
                        'model' => $list->model,
                        'descr' => $list->descr,
                        'year' => $list->year,
                        'price' => $list->price,
                        'created_by' => $request->user()->id,
                        'created_at' => $this->timeNow
                    ];

                    M_TaksasiBak::create($log);
                });

                M_Taksasi::query()->delete();
                M_TaksasiPrice::query()->delete();
            }

            $insertData = [];
            $dataExist = [];

            foreach ($vehicles as $vehicle) {
                $uuid = Uuid::uuid7()->toString();

                // Create a unique key using all relevant fields
                $uniqueKey = $vehicle['brand'] . '-' . $vehicle['vehicle'] . '-' . $vehicle['type'] . '-' . $vehicle['model'];

                // Format the price consistently
                $formattedPrice = number_format(floatval(str_replace(',', '', $vehicle['price'] ?? '0')), 0, '.', '');

                if (!isset($dataExist[$uniqueKey])) {
                    // First occurrence of this vehicle combination
                    $insertData[] = [
                        'id' => $uuid,
                        'brand' => $vehicle['brand'] ?? '',
                        'code' => $vehicle['vehicle'] ?? '',
                        'model' => $vehicle['type'] ?? '',
                        'descr' => $vehicle['model'] ?? '',
                        'year' => [
                            [
                                'year' => $vehicle['year'] ?? '',
                                'price' => $formattedPrice
                            ]
                        ],
                        'create_by' => $request->user()->id,
                        'create_at' => now(),
                    ];

                    // Store the index of this entry
                    $dataExist[$uniqueKey] = count($insertData) - 1;
                } else {
                    // Vehicle combination already exists, add new year and price
                    $existingIndex = $dataExist[$uniqueKey];

                    // Check if this year entry already exists
                    $yearExists = false;
                    foreach ($insertData[$existingIndex]['year'] as $yearEntry) {
                        if ($yearEntry['year'] === $vehicle['year']) {
                            $yearExists = true;
                            break;
                        }
                    }

                    // Only add if this year doesn't exist yet
                    if (!$yearExists) {
                        $insertData[$existingIndex]['year'][] = [
                            'year' => $vehicle['year'] ?? '',
                            'price' => $formattedPrice
                        ];
                    }
                }
            }

            if (count($insertData) > 0) {
                foreach ($insertData as $data) {
                    M_Taksasi::insert([
                        'id' => $data['id'],
                        'brand' => $data['brand'],
                        'code' => $data['code'],
                        'model' => $data['model'],
                        'descr' => $data['descr'],
                        'create_by' => $data['create_by'],
                        'create_at' => $data['create_at'],
                    ]);

                    foreach ($data['year'] as $yearData) {
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
            return response()->json(['message' => 'updated successfully'], 200);
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
                ->select('a.brand', 'a.code', 'a.model', 'a.descr', 'b.year', DB::raw('CAST(b.price AS UNSIGNED) AS price'))
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

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\M_AdminFee;
use App\Models\M_AdminType;
use App\Models\M_InterestDecreasesSetting;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFeeController extends Controller
{
    protected $adminfee;
    protected $log;

    public function __construct(M_AdminFee $admin_fee, ExceptionHandling $log)
    {
        $this->adminfee = $admin_fee;
    }

    public function index(Request $request)
    {
        try {
            $data = M_AdminFee::with('links')->get();
            $show = $this->buildArray($data);

            return response()->json($show, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $data = M_AdminFee::with('links')->where('id', $id)->get();

            if ($data->isEmpty()) {
                throw new Exception("Data Not Found", 404);
            }

            $show = $this->buildArray($data);

            return response()->json($show, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $checkRange =   M_AdminFee::where('category', 'bulanan')
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_value', '<', $request->start_value)
                            ->where('end_value', '>', $request->start_value);
                    })->orWhere(function ($q) use ($request) {
                        $q->where('start_value', '<', $request->end_value)
                            ->where('end_value', '>', $request->end_value);
                    });
                })->get();

            if (!$checkRange->isEmpty()) {
                throw new Exception("Data Range Sudah Ada");
            }

            $data_admin_fee = [
                'category' => $request->kategory,
                'start_value' => $request->start_value,
                'end_value' => $request->end_value
            ];

            $admin_fee_id = M_AdminFee::create($data_admin_fee);

            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $value) {
                    $data_admin_type = [
                        'admin_fee_id' => $admin_fee_id->id,
                        'fee_name' => isset($value['key']) ? $value['key'] : '',
                        '6_month' => $value['tenor6'],
                        '12_month' => $value['tenor12'],
                        '18_month' => $value['tenor18'],
                        '24_month' => $value['tenor24']
                    ];

                    M_AdminType::create($data_admin_type);
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

            $admin_fee = M_AdminFee::find($id);

            if (!$admin_fee) {
                throw new Exception("Data Not Found", 404);
            }

            $data_admin_fee = [
                'category' => $request->kategory,
                'start_value' => $request->start_value,
                'end_value' => $request->end_value
            ];

            $admin_fee->update($data_admin_fee);

            if (M_AdminType::where('admin_fee_id', $id)->exists()) {
                M_AdminType::where('admin_fee_id', $id)->delete();
            }

            if (isset($request->struktur) && is_array($request->struktur)) {
                foreach ($request->struktur as $value) {
                    $data_admin_type = [
                        'admin_fee_id' => $id,
                        'fee_name' => isset($value['key']) ? $value['key'] : '',
                        '6_month' => $value['tenor6'],
                        '12_month' => $value['tenor12'],
                        '18_month' => $value['tenor18'],
                        '24_month' => $value['tenor24']
                    ];

                    M_AdminType::create($data_admin_type);
                }
            }

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function fee_survey(Request $request)
    {
        try {
            $plafond = intval($request->plafond) / 1000000;
            $angsuran_type = strtolower($request->jenis_angsuran);

            if ($plafond == null || $plafond == 0 || empty($plafond)) {
                $adminFee = M_AdminFee::with('links')->get();
            } else {
                $adminFee = $this->adminfee->checkRange($plafond, $angsuran_type);
            }

            if ($angsuran_type == 'musiman') {
                $show = $this->buildArrayMusiman(
                    $adminFee,
                    [
                        'returnSingle' => true,
                        'plafond' => $request->plafond,
                        'angsuran_type' => $angsuran_type
                    ]
                );
            } elseif ($angsuran_type == 'bulanan') {
                $show = $this->buildArray(
                    $adminFee,
                    [
                        'returnSingle' => true,
                        'plafond' => $request->plafond,
                        'angsuran_type' => $angsuran_type
                    ]
                );
            } else {
                $show = $this->buildArrayBungaMenurun(
                    $adminFee,
                    [
                        'returnSingle' => true,
                        'plafond' => $request->plafond,
                        'angsuran_type' => $angsuran_type
                    ]
                );
            }

            return response()->json($show, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function fee(Request $request)
    {
        try {
            $plafond = (int) $request->plafond / 1000000;
            $angsuran_type = strtolower($request->jenis_angsuran);
            $tenor = $request->tenor;

            $adminFee = $this->adminfee->checkRange($plafond, $angsuran_type);

            if ($tenor == 0) {
                $show = [];
            } else {
                if ($angsuran_type == 'bunga_menurun') {
                    $show = $this->buildArrayBungaMenurun(
                        $adminFee,
                        [
                            'returnSingle' => true,
                            'type' => 'fee',
                            'plafond' => $request->plafond,
                            'angsuran_type' => $angsuran_type
                        ]
                    );
                } else {
                    $show = $this->buildArray(
                        $adminFee,
                        [
                            'returnSingle' => true,
                            'type' => 'fee',
                            'tenor' => (int) $tenor,
                            'angsuran_type' => $angsuran_type,
                            'plafond' => $request->plafond,
                        ]
                    );
                }
            }

            return response()->json($show, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function buildArray($data, $options = [])
    {
        $returnSingle = $options['returnSingle'] ?? false;
        $specificTenor = $options['tenor'] ?? null;
        $plafond = $options['plafond'] ?? null;
        $angsuran_type = $options['angsuran_type'] ?? null;

        $build = [];

        $tenorList = [
            '3' => 6,
            '6' => 12,
            '12' => 18,
            '18' => 24,
        ];

        foreach ($data as $value) {

            if ($angsuran_type === 'bulanan') {
                $strukturTenors = $this->buildStrukturBulanan($value->links, $specificTenor, $plafond);
            } else {
                $strukturTenors = $this->buildStrukturMusiman($value->links, $tenorList[$specificTenor], $plafond);
            }

            $item = [
                'id' => $value->id,
                'tipe' => $value->category,
                'range_start' => (float) $value->start_value,
                'range_end' => (float) $value->end_value,
            ];

            if ($specificTenor) {
                if ($angsuran_type === 'bulanan') {
                    $item += $strukturTenors["tenor_$specificTenor"];
                } else {
                    $tenorKey = $tenorList[$specificTenor];
                    $item += $strukturTenors["tenor_$tenorKey"];
                }
            } else {
                $item += $strukturTenors;
            }

            if ($returnSingle) {
                return $item;
            }

            $build[] = $item;
        }

        return $data;
    }

    public function buildArrayMusiman($data, $options = [])
    {
        $returnSingle = $options['returnSingle'] ?? false;
        $specificTenor = $options['tenor'] ?? null;
        $plafond = $options['plafond'] ?? null;
        $angsuran_type = strtolower($options['angsuran_type']) ?? null;

        $build = [];

        $tenorList = [
            '3' => 6,
            '6' => 12,
            '12' => 18,
            '18' => 24,
        ];

        foreach ($data as $value) {

            $strukturTenors = $this->buildStrukturMusiman($value->links, $specificTenor, $plafond);

            $item = [
                'id' => $value->id,
                'tipe' => $value->category,
                'range_start' => (float) $value->start_value,
                'range_end' => (float) $value->end_value,
            ];

            if ($specificTenor) {
                if ($angsuran_type === 'bulanan') {
                    $item += $strukturTenors["tenor_$specificTenor"];
                } else {
                    $tenorKey = $tenorList[$specificTenor];
                    $item += $strukturTenors["tenor_$tenorKey"];
                }
            } else {
                $item += $strukturTenors;
            }

            if ($returnSingle) {
                return $item;
            }

            $build[] = $item;
        }

        return $data;
    }

    public function buildArrayBungaMenurun($data, $options = [])
    {
        $returnSingle = $options['returnSingle'] ?? false;
        $specificTenor = $options['tenor'] ?? null;
        $plafond = $options['plafond'] ?? null;

        $build = [];
        foreach ($data as $value) {
            $strukturTenors = $this->buildStrukturBungaMenurun($value->links, $specificTenor, $plafond);

            $item = [
                'id' => $value->id,
                'tipe' => $value->category,
                'range_start' => (int) $value->start_value,
                'range_end' => (int) $value->end_value,
            ];

            if ($specificTenor) {
                $tenorKey = $specificTenor;
                $item += $strukturTenors["tenor_$tenorKey"];
            } else {
                $item += $strukturTenors;
            }

            if ($returnSingle) {
                return $item;
            }

            $build[] = $item;
        }

        return $build;
    }

    private function buildStrukturBulanan($links, $specificTenor = null, $plafond)
    {
        $struktur = $links->map(function ($link) {
            return [
                'fee_name' => $link['fee_name'],
                '6_month' =>  floatval($link['6_month']),
                '12_month' =>  floatval($link['12_month']),
                '18_month' =>  floatval($link['18_month']),
                '24_month' =>  floatval($link['24_month']),
            ];
        })->toArray();

        $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

        $strukturTenors = [];

        foreach ($tenors as $tenor) {
            $tenorData = ['tenor' => strval($tenor)];
            $total = 0;
            $tenor_name = $tenor . '_month';

            foreach ($struktur as $s) {
                $feeName = $s['fee_name'];
                $feeValue = (float) $s[$tenor_name];

                $tenorData[$feeName] = $feeValue;

                if ($feeName !== 'eff_rate') {
                    $total += $feeValue;
                }
            }

            $set_tenor = $tenor;

            $pokok_pembayaran = ($plafond + $total);

            $eff_rate = $tenorData['eff_rate'];
            // $flat_rate = round($this->calculate_flat_interest($set_tenor, $eff_rate), 2);
            $flat_rate = (($set_tenor * (($eff_rate / 100) / 12) / (1 - pow((1 + (($eff_rate / 100) / 12)), (-$set_tenor)))) - 1) * (12 / $set_tenor) * 100;
            $interest_margin = intval(($flat_rate / 12) * $set_tenor * $pokok_pembayaran / 100);

            if (!in_array($set_tenor, ['6', '12', '18', '24'])) {
                $angsuran_calc = 0;
            } else {
                $angsuran_calc = ($pokok_pembayaran + $interest_margin) / $set_tenor;
            }

            $setAngsuran = ceil(round($angsuran_calc, 3) / 1000) * 1000;

            $number = excelRate($set_tenor, -$setAngsuran, $pokok_pembayaran) * 100;
            $suku_bunga = ((12 * ($setAngsuran - ($pokok_pembayaran / $set_tenor))) / $pokok_pembayaran) * 100;

            $tenorData['angsuran'] = $setAngsuran;
            $tenorData['suku_bunga'] = $suku_bunga;
            $tenorData['total_bunga'] = round(($pokok_pembayaran * ($suku_bunga / 100) / 12) * $set_tenor, 2);
            $tenorData['flat_rate'] = round($number, 10);
            $tenorData['eff_rate'] = round($number * 12, 8);
            $tenorData['total'] = $total;
            $strukturTenors["tenor_$tenor"] = $tenorData;
        }

        $datas = !empty($plafond) ? $strukturTenors : [];

        return $datas;
    }

    private function buildStrukturMusiman($links, $specificTenor = null, $plafond)
    {
        $struktur = $links->map(function ($link) {
            return [
                'fee_name' => $link['fee_name'],
                '6_month' =>  floatval($link['6_month']),
                '12_month' =>  floatval($link['12_month']),
                '18_month' =>  floatval($link['18_month']),
                '24_month' =>  floatval($link['24_month']),
            ];
        })->toArray();

        $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

        $strukturTenors = [];

        $tenorList = [
            '6' => 3,
            '12' => 6,
            '18' => 12,
            '24' => 18,
        ];

        foreach ($tenors as $tenor) {
            $tenorData = ['tenor' => strval($tenorList[$tenor])];
            $total = 0;
            $tenor_name = $tenor . '_month';

            foreach ($struktur as $s) {
                $feeName = $s['fee_name'];
                $feeValue = (float) $s[$tenor_name];

                $tenorData[$feeName] = $feeValue;

                if ($feeName !== 'eff_rate') {
                    $total += $feeValue;
                }
            }

            $set_tenor = $tenor;

            $pokok_pembayaran = ($plafond + $total);

            $eff_rate = $tenorData['eff_rate'];

            if (!in_array($set_tenor, ['6', '12', '18', '24'])) {
                $angsuran_calc = 0;
            } else {
                $set_tenor = $tenorList[$tenor] ?? 0;

                if ($set_tenor == 12 || $set_tenor == 18) {
                    $eff_rate = $this->calculate($set_tenor, $tenorData['eff_rate'] / 100) * 100;
                } else {
                    $eff_rate = $tenorData['eff_rate'];
                }

                $angsuran_calc = $this->angsuran($plafond, $total, $this->calculateBunga($eff_rate, $set_tenor), $set_tenor);
            }

            $setAngsuran = $angsuran_calc;

            $tenorLists = [
                '3' => 1,
                '6' => 1,
                '12' => 2,
                '18' => 3,
            ];

            $set_tenor = $tenorLists[$set_tenor];

            $number = excelRate($set_tenor, -$setAngsuran, $pokok_pembayaran) * 100;
            $suku_bunga = ((12 * ($setAngsuran - ($pokok_pembayaran / $set_tenor))) / $pokok_pembayaran) * 100;

            $tenorData['angsuran'] = $setAngsuran;
            $tenorData['suku_bunga'] = $suku_bunga;
            $tenorData['total_bunga'] = round(($pokok_pembayaran * ($suku_bunga / 100) / 12) * $set_tenor, 2);
            $tenorData['flat_rate'] = round($number, 10);
            $tenorData['eff_rate'] = round($number * 12, 8);
            $tenorData['total'] = $total;
            $strukturTenors["tenor_$tenor"] = $tenorData;
        }

        $datas = !empty($plafond) ? $strukturTenors : [];

        return $datas;
    }

    private function buildStrukturBungaMenurun($links, $specificTenor = null, $plafond)
    {
        $struktur = $links->map(function ($link) {
            return [
                'fee_name' => $link['fee_name'],
                '6_month' =>  floatval($link['6_month']),
                '12_month' =>  floatval($link['12_month']),
                '18_month' =>  floatval($link['18_month']),
                '24_month' =>  floatval($link['24_month']),
            ];
        })->toArray();

        $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

        $strukturTenors = [];

        foreach ($tenors as $tenor) {
            $tenorData = ['tenor' => strval($tenor)];
            $total = 0;
            $tenor_name = $tenor . '_month';

            foreach ($struktur as $s) {
                $feeName = $s['fee_name'];
                $feeValue = (int) $s[$tenor_name];

                $tenorData[$feeName] = $feeValue;

                if ($feeName !== 'eff_rate') {
                    $total += $feeValue;
                }
            }

            $pokok_pembayaran = ($plafond + $total);
            $eff_rate = $tenorData['eff_rate'];

            $calc = floatval($pokok_pembayaran * ($tenor / 12) * ($eff_rate / 100));

            if ($calc > 0) {
                $tenorData['angsuran'] = $calc;
                $tenorData['suku_bunga'] = $eff_rate;
                $tenorData['total_bunga'] = 0;
                $tenorData['flat_rate'] = 0;
                $tenorData['eff_rate'] = 0;
                $tenorData['total'] = $total;
                $strukturTenors["tenor_$tenor"] = $tenorData;
            }
        }

        $datas = !empty($plafond) ? $strukturTenors : [];

        return $datas;
    }

    function angsuran($pokok, $admin, $bunga, $tenor)
    {

        if ($tenor == 3 || $tenor == 6) {
            $c4 = 1;
        } elseif ($tenor == 12) {
            $c4 = 2;
        } else {
            $c4 = 3;
        }

        $result = (($pokok + $admin) * (1 + $bunga / 100)) / $c4;

        $roundedResult = ceil($result / 1000) * 1000;

        return $roundedResult;
    }

    function calculateBunga($flat, $tenor)
    {
        return $flat * ($tenor / 12);
    }

    function calculate($e5, $e7)
    {
        return ((($e5 * ($e7 / 12)) / (1 - pow(1 + ($e7 / 12), -$e5))) - 1) * (12 / $e5) + 0.1;
    }

    function hitungCicilanFlatRate($plafond, $effRate, $tenor)
    {
        $bungaPerTahun = $plafond * ($effRate / 100);

        $bungaTotal = round(($bungaPerTahun / 12) * $tenor, -3);

        return $bungaTotal;
    }

    function calculate_flat_interest($tenor, $eff_rate)
    {
        $eff_rate_decimal = $eff_rate / 100;
        $monthly_eff_rate = $eff_rate_decimal / 12;

        $compounded_factor = pow(1 + $monthly_eff_rate, -$tenor);
        $numerator = $tenor * $monthly_eff_rate;
        $denominator = 1 - $compounded_factor;
        if ($denominator != 0) {
            return (($numerator / $denominator) - 1) * (12 / $tenor) * 100;
        } else {
            return 0;
        }
    }
}

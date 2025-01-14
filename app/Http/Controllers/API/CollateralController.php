<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use Illuminate\Http\Request;

class CollateralController extends Controller
{
    public function index(Request $request)
    {
        try {

            $search = $request->get('search');
            
            $collateral = M_CrCollateral::where(function($query) {
                                $query->whereNull('DELETED_AT')
                                    ->orWhere('DELETED_AT', '');
                            });  

            $collateralSertification = M_CrCollateralSertification::where(function($query) {
                                        $query->whereNull('DELETED_AT')
                                            ->orWhere('DELETED_AT', '');
                                    })->paginate(10); 

            if(isset($search)){
                $collateral->where(function($query) use ($search) {
                    $query->where('ON_BEHALF', 'LIKE', "%{$search}%")
                        ->orWhere('POLICE_NUMBER', 'LIKE', "%{$search}%")
                        ->orWhere('CHASIS_NUMBER', 'LIKE', "%{$search}%")
                        ->orWhere('ENGINE_NUMBER', 'LIKE', "%{$search}%")
                        ->orWhere('BPKB_NUMBER', 'LIKE', "%{$search}%")
                        ->orWhere('STNK_NUMBER', 'LIKE', "%{$search}%");
                });

                $collateralSertification->where(function($query) use ($search) {
                    $query->where('NO_SERTIFIKAT', 'LIKE', "%{$search}%")
                        ->orWhere('ATAS_NAMA', 'LIKE', "%{$search}%");
                });
            }

            $collateral = $collateral->paginate(10);  

            $collateralData = $collateral->getCollection()->transform(function ($list) {
                return [
                    "type" => "kendaraan",
                    'id' => $list->ID,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    "asal_lokasi" => M_Branch::find($list->COLLATERAL_FLAG)->NAME??null,
                    "lokasi" => M_Branch::find($list->LOCATION_BRANCH)->NAME??$list->LOCATION_BRANCH,
                ];
            });

            $collateralSertificatData = $collateralSertification->getCollection()->transform(function ($list) {
                return [
                  "type" => "sertifikat",
                    'id' => $list->ID,
                    "no_sertifikat" => $list->NO_SERTIFIKAT,
                    "status_kepemilikan" => $list->STATUS_KEPEMILIKAN,
                    "imb" => $list->IMB,
                    "luas_tanah" => $list->LUAS_TANAH,
                    "luas_bangunan" => $list->LUAS_BANGUNAN,
                    "lokasi" => $list->LOKASI,
                    "provinsi" => $list->PROVINSI,
                    "kab_kota" => $list->KAB_KOTA,
                    "kec" => $list->KECAMATAN,
                    "desa" => $list->DESA,
                    "atas_nama" => $list->ATAS_NAMA,
                    "nilai" => (int) $list->NILAI,
                    "lokasi" => M_Branch::find($list->LOCATION_BRANCH)->NAME??null
                ];
            });
    
            $data = array_merge($collateralData->toArray(), $collateralSertificatData->toArray());

            $response = [
                'data' => $data,
                'pagination' => [
                    'total' => $collateral->total() + $collateralSertification->total(), // Total combined
                    'current_page' => max($collateral->currentPage(), $collateralSertification->currentPage()),
                    'last_page' => max($collateral->lastPage(), $collateralSertification->lastPage()),
                    'per_page' => 10,
                    'from' => $collateral->firstItem(),
                    'to' => $collateral->lastItem(),
                    'links' => [
                        'first' => $collateral->url(1),
                        'last' => $collateral->url($collateral->lastPage()),
                        'prev' => $collateral->previousPageUrl(),
                        'next' => $collateral->nextPageUrl(),
                    ],
                ],
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

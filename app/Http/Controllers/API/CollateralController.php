<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollateralController extends Controller
{
    public function index(Request $request)
    {
        try {
            $atas_nama = $request->query('atas_nama');
            $no_polisi = $request->query('no_polisi');
            $no_bpkb = $request->query('no_bpkb');

            $collateral = M_CrCollateral::where(function ($query) {
                $query->whereNull('DELETED_AT')
                    ->orWhere('DELETED_AT', '');
            });

            // Apply filters only if the respective parameter is not empty
            if (!empty($atas_nama)) {
                $collateral = $collateral->where('ON_BEHALF', 'like', '%' . $atas_nama . '%');
            }

            if (!empty($no_polisi)) {
                $collateral = $collateral->where('POLICE_NUMBER', 'like', '%' . $no_polisi . '%');
            }

            if (!empty($no_bpkb)) {
                $collateral = $collateral->where('BPKB_NUMBER', 'like', '%' . $no_bpkb . '%');
            }

            // Limit the result to 10 records right away
            $collateral = $collateral->limit(10);

            // Use get() to retrieve the data, which will return a Collection
            $collateralData = $collateral->get()->transform(function ($list) {
                return $this->collateralField($list);
            });

            $collateralData = $collateralData->toArray();

            // Return the transformed data as JSON
            return response()->json($collateralData, 200);            
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $checkCollateral = M_CrCollateral::where('id',$id)->first();

            if(!$checkCollateral){
                throw new Exception('Collateral Not Found',404);
            }

            $datas = $this->collateralField($checkCollateral);
          
            return response()->json($datas, 200);
        }  catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $checkCollateral = M_CrCollateral::where('id',$id)->first();

            if (!$checkCollateral) {
                throw new Exception('Collateral Not Found', 404);
            }

            $data = [
                'BRAND' => $request->merk ?? '',
                'TYPE' => $request->tipe ?? '',
                'PRODUCTION_YEAR' => $request->tahun ?? '',
                'COLOR' => $request->warna ?? '',
                'ON_BEHALF' => $request->atas_nama ?? '',
                'POLICE_NUMBER' => $request->no_polisi ?? '',
                'CHASIS_NUMBER' => $request->no_rangka ?? '',
                'ENGINE_NUMBER' => $request->no_mesin ?? '',
                'BPKB_NUMBER' => $request->no_bpkb ?? '',
                'STNK_NUMBER' => $request->no_stnk ?? '',
                'MOD_DATE' => Carbon::now()->format('Y-m-d H:i:s') ?? '',
                'MOD_BY' => $request->user()->id ?? '',
            ];

            $checkCollateral->update($data);

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Cabang updated successfully', "status" => 200], 200);
        }  catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    private function collateralField($list){
        return [
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
    }

    private function collateralSertificationField($list){
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
    }

    private function pagination($collateral){
         return [
            'current_page' => $collateral->currentPage(),
            'total_pages' => $collateral->lastPage(),
            'total_items' => $collateral->total(),
            'per_page' => $collateral->perPage(),
            'next_page_url' => $collateral->nextPageUrl(),
            'prev_page_url' => $collateral->previousPageUrl(),
            'first_page_url' => $collateral->url(1),
            'last_page_url' => $collateral->url($collateral->lastPage()),
            'links' => $this->getPaginationLinks($collateral)
        ];
    }

    private function getPaginationLinks($paginator)
    {
        $links = [];

        // Previous link
        $links[] = [
            'url' => $paginator->previousPageUrl(),
            'label' => 'Previous',
            'active' => false
        ];

        // Page links
        for ($page = 1; $page <= $paginator->lastPage(); $page++) {
            $links[] = [
                'url' => $paginator->url($page),
                'label' => (string) $page,
                'active' => $page == $paginator->currentPage()
            ];
        }

        // Next link
        $links[] = [
            'url' => $paginator->nextPageUrl(),
            'label' => 'Next',
            'active' => false
        ];

        return $links;
    }
}

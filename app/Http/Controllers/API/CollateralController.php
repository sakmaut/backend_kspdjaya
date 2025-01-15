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
            $type = $request->get('type');

            switch ($type) {
                case 'kendaraan':
                    $collateral = M_CrCollateral::where(function($query) {
                        $query->whereNull('DELETED_AT')
                            ->orWhere('DELETED_AT', '');
                    });  

                    if(isset($search)){
                        $collateral->where(function($query) use ($search) {
                            $query->where('ON_BEHALF', 'LIKE', "%{$search}%")
                                ->orWhere('POLICE_NUMBER', 'LIKE', "%{$search}%")
                                ->orWhere('CHASIS_NUMBER', 'LIKE', "%{$search}%")
                                ->orWhere('ENGINE_NUMBER', 'LIKE', "%{$search}%")
                                ->orWhere('BPKB_NUMBER', 'LIKE', "%{$search}%")
                                ->orWhere('STNK_NUMBER', 'LIKE', "%{$search}%");
                        });
                    }

                    if ($collateral->count() > 0) {
                        $collateral = $collateral->paginate(10);

                        $collateralData = $collateral->getCollection()->transform(function ($list) {
                            return $this->collateralField($list);
                        });
                    
                        $collateralData = $collateralData->toArray();

                        return response()->json([
                            'data' => $collateralData,
                            'pagination' => $this->pagination($collateral),
                        ], 200);  
                    }
  
                    break;
                default:
                     return response()->json([],200);
                    break;
            }

            // $collateralSertification = M_CrCollateralSertification::where(function($query) {
            //                             $query->whereNull('DELETED_AT')
            //                                 ->orWhere('DELETED_AT', '');
            //                 }); 

            // if(isset($search)){
            //     $collateral->where(function($query) use ($search) {
            //         $query->where('ON_BEHALF', 'LIKE', "%{$search}%")
            //             ->orWhere('POLICE_NUMBER', 'LIKE', "%{$search}%")
            //             ->orWhere('CHASIS_NUMBER', 'LIKE', "%{$search}%")
            //             ->orWhere('ENGINE_NUMBER', 'LIKE', "%{$search}%")
            //             ->orWhere('BPKB_NUMBER', 'LIKE', "%{$search}%")
            //             ->orWhere('STNK_NUMBER', 'LIKE', "%{$search}%");
            //     });

            //     $collateralSertification->where(function($query) use ($search) {
            //         $query->where('NO_SERTIFIKAT', 'LIKE', "%{$search}%")
            //             ->orWhere('ATAS_NAMA', 'LIKE', "%{$search}%");
            //     });
            // }

            // $collateralSertificatData = [];
            // if ($collateralSertification->count() > 0) {
            //     $collateralSertification = $collateralSertification->paginate(10);
            //     $collateralSertificatData = $collateralSertification->getCollection()->map(function ($list) {
            //         $this->collateralSertificationField($list);  // Apply necessary transformation
            //         return $list;  // Ensure the transformed item is returned
            //     });
            //     // Convert the collection to an array
            //     $collateralSertificatData = $collateralSertificatData->toArray(); 
            // }

            // // Now you can merge the arrays properly
            // $data = array_merge($collateralData, $collateralSertificatData);
            
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $checkCollateral = M_CrCollateral::where('id',$id)->first();
            $checkCollateralSertification = M_CrCollateralSertification::where('id',$id)->first();

            if($checkCollateral){
               $this->collateralField($checkCollateral);
            }

            if($checkCollateralSertification){
                $this->collateralSertificationField($checkCollateralSertification);
            }
          

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json('', 200);
        }  catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function collateralField($list){
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

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_BpkbDetail;
use App\Models\M_Branch;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralDocument;
use App\Models\M_CrCollateralDocumentRelease;
use App\Models\M_CrCollateralSertification;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class CollateralController extends Controller
{
    public function index(Request $request)
    {
        try {
            $atas_nama = $request->query('atas_nama');
            $no_polisi = $request->query('no_polisi');
            $no_bpkb = $request->query('no_bpkb');

            $collateral = DB::table('credit as a')
                                ->join('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
                                ->where(function ($query) {
                                    $query->whereNull('b.DELETED_AT')
                                    ->orWhere('b.DELETED_AT', '!=', '');
                                })
                                ->where('a.STATUS', 'A')
                                ->select(
                                    'a.LOAN_NUMBER',
                                    'b.ID',
                                    'b.BRAND',
                                    'b.TYPE',
                                    'b.PRODUCTION_YEAR',
                                    'b.COLOR',
                                    'b.ON_BEHALF',
                                    'b.ENGINE_NUMBER',
                                    'b.POLICE_NUMBER',
                                    'b.CHASIS_NUMBER',
                                    'b.BPKB_ADDRESS',
                                    'b.BPKB_NUMBER',
                                    'b.STNK_NUMBER',
                                    'b.INVOICE_NUMBER',
                                    'b.STNK_VALID_DATE',
                                    'b.VALUE'
                                );

            if (!empty($atas_nama)) {
                $collateral->where('b.ON_BEHALF', 'like', '%' . $atas_nama . '%');
            }

            if (!empty($no_polisi)) {
                $collateral->where('b.POLICE_NUMBER', 'like', '%' . $no_polisi . '%');
            }

            if (!empty($no_bpkb)) {
                $collateral->where('b.BPKB_NUMBER', 'like', '%' . $no_bpkb . '%');
            }

            $collateral->orderBy('a.CREATED_AT', 'DESC');

            // Limit the result to 10 records
            $collateral->limit(10);

            $collateralData = []; // Initialize an empty array to store the results

            // Fetch the collateral data
            $collateralResults = $collateral->get(); // Call get() once

            // Check if data exists
            if ($collateralResults->isNotEmpty()) {
                foreach ($collateralResults as $value) {
                    $collateralData[] = [  // Append each item to the array
                        'loan_number'       => $value->LOAN_NUMBER,
                        'id'                => $value->ID,
                        'merk'              => $value->BRAND,
                        'tipe'              => $value->TYPE,
                        'tahun'             => $value->PRODUCTION_YEAR,
                        'warna'             => $value->COLOR,
                        'atas_nama'         => $value->ON_BEHALF,
                        'no_polisi'         => $value->POLICE_NUMBER,
                        'no_mesin'          => $value->ENGINE_NUMBER,
                        'no_rangka'         => $value->CHASIS_NUMBER,
                        'BPKB_ADDRESS'      => $value->BPKB_ADDRESS,
                        'no_bpkb'           => $value->BPKB_NUMBER,
                        'no_stnk'           => $value->STNK_NUMBER,
                        'no_faktur'         => $value->INVOICE_NUMBER,
                        'tgl_stnk'          => $value->STNK_VALID_DATE,
                        'nilai'             => $value->VALUE,
                        'asal_lokasi'       => $value->VALUE
                    ];
                }
            }
        
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

    public function uploadImage(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'collateral_id' => 'required|string'
            ]);

            // Decode the base64 string
            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid4()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Collateral/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);
                
                $url = URL::to('/') . '/storage/' . 'Cr_Collateral/' . $fileName;

                $collateral = [
                    'COLLATERAL_ID' => $req->collateral_id,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => round(microtime(true) * 1000),
                    'PATH' => $url ?? ''
                ];

                M_CrCollateralDocument::create($collateral);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function uploadImageRelease(Request $req)
    {
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'required|string',
                'type' => 'required|string',
                'uid' => 'required|string'
            ]);

            if (preg_match('/^data:image\/(\w+);base64,/', $req->image, $type)) {
                $data = substr($req->image, strpos($req->image, ',') + 1);
                $data = base64_decode($data);

                // Generate a unique filename
                $extension = strtolower($type[1]); // Get the image extension
                $fileName = Uuid::uuid7()->toString() . '.' . $extension;

                // Store the image
                $image_path = Storage::put("public/Cr_Collateral_Release/{$fileName}", $data);
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . 'Cr_Collateral_Release/' . $fileName;

                $collateral = [
                    'COLLATERAL_ID' => $req->uid,
                    'TYPE' => $req->type,
                    'COUNTER_ID' => round(microtime(true) * 1000),
                    'PATH' => $url ?? '',
                    'CREATED_BY' => $req->user()->id ?? '',
                    'CREATED_AT' => Carbon::now() ?? null
                ];

                M_CrCollateralDocumentRelease::create($collateral);

                // $checkBpkbDetail = M_BpkbDetail::where()->first();

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided'], 400);
            }
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

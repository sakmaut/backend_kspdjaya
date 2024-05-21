<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_Branch;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrProspect;
use App\Models\M_CrProspectDocument;
use App\Models\M_HrEmployee;
use App\Models\M_ProspectApproval;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class CrprospectController extends Controller
{
    public function index(Request $req){
        try {
            $ao_id = $req->user()->id;
            $data =  M_CrProspect::whereNull('deleted_at')->where('ao_id', $ao_id)->get();
    
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (QueryException $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $check = M_CrProspect::where('id',$id)->whereNull('deleted_at')->firstOrFail();

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => self::resourceDetail($req,$check)], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function resourceDetail($request,$data)
    {
        $prospect_id = $data->id;
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_PROSPECT_ID',$prospect_id)->get(); 
        $approval_detail = M_ProspectApproval::where('CR_PROSPECT_ID',$prospect_id)->first();
        $attachment_data = M_CrProspectDocument::where('CR_PROSPECT_ID',$prospect_id )->get();
        
        $arrayList = [
            'id' => $prospect_id,
            'visit_date' => date('d-m-Y',strtotime($data->visit_date)),
            'cust_code_ref' => $data->cust_code_ref,
            'data_ao' =>[
                'ao_id' => $request->user()->id,
                'nama_ao' => M_HrEmployee::findEmployee($request->user()->employee_id)->NAMA,
            ],
            'data_order' =>[
                'tujuan_kredit' => $data->tujuan_kredit,
                'plafond' => 'IDR '.number_format($data->plafond,0,",","."),
                'tenor' => $data->tenor, 
                'kategory' => $data->category 
            ],
            'data_nasabah' => [
                'nama' => $data->nama,
                'tgl_lahir' => date('d-m-Y',strtotime($data->tgl_lahir)),
                'no_hp' => $data->hp,
                'no_ktp' => $data->ktp,
                'data_alamat' => [
                    'alamat' => $data->alamat,
                    'rt' => $data->rt,
                    'rw' => $data->rw,
                    'provinsi' => $data->province,
                    'kota' => $data->city,
                    'kelurahan' => $data->kelurahan,
                    'kecamatan' => $data->kecamatan,
                    'kode_pos' => $data->zip_code
                ],
            ], 
            'data_survey' =>[
                'usaha' => $data->usaha,
                'sektor' => $data->sector,
                'lama_bekerja' => $data->work_period,
                'tanggungan' => $data->dependants,
                'pengeluaran' => $data->expenses,
                'penghasilan' => [
                    'pribadi' => $data->income_personal,
                    'pasangan' => $data->income_spouse,
                    'lainnya' => $data->income_other,
                ]
            ],
            "lokasi" => [ 
                'coordinate' => $data->coordinate,
                'accurate' => $data->accurate
            ],         
            'jaminan_kendaraan' => $guarente_vehicle,
            'prospect_approval' => [
                'flag_approval' => $approval_detail->ONCHARGE_APPRVL,
                'keterangan' => $approval_detail->ONCHARGE_DESCR,
                'status' => $approval_detail->APPROVAL_RESULT
            ],
            "attachment" =>$attachment_data
        ];
        
        return $arrayList;
    }
    
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'id' => 'required|string|unique:cr_prospect',
                // 'order.plafond' => 'numeric',
                // 'order.tenor' => 'numeric',
                // 'data_nasabah.no_ktp' => 'numeric',
                // 'data_nasabah.tgl_lahir' => 'date',
                // 'data_nasabah.no_hp' => 'numeric',
                // "data_survey.penghasilan.pribadi" => "numeric",
                // "data_survey.penghasilan.pasangan" => "numeric",
                // "data_survey.penghasilan.lainnya" => "numeric",
                // "data_survey.pengeluaran" => "numeric",
                // "data_survey.tgl_survey" => "date"
            ]);

            $data = self::createCrProspek($request);
            self::createCrProspekApproval($request);

            if (collect($request->jaminan_kendaraan)->isNotEmpty()) {
                self::insert_cr_vehicle($request);
            }
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Kunjungan created successfully',"status" => 200], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function createCrProspek($request)
    {
        $data_array = [
            'id' => $request->id,
            'ao_id' => $request->user()->id,
            'branch_code' => M_HrEmployee::where('ID',$request->user()->employee_id)->first()->BRANCH_CODE,
            'visit_date' => isset($request->data_survey['catatan_survey']) && !empty($request->data_survey['catatan_survey'])?$request->data_survey['catatan_survey']:null,
            'tujuan_kredit' => $request->order['tujuan_kredit']?? null,
            'plafond' => $request->order['plafond']?? null,
            'tenor' => $request->order['tenor']?? null,
            'category' => $request->order['category']?? null,
            'nama' => $request->data_nasabah['nama']?? null,
            'tgl_lahir' => $request->data_nasabah['tgl_lahir']?? null,
            'ktp' => $request->data_nasabah['no_ktp']?? null,
            'hp' => $request->data_nasabah['no_hp']?? null,
            'alamat' => $request->data_nasabah['data_alamat']['alamat']?? null,
            'rt' => $request->data_nasabah['data_alamat']['rt']?? null,
            'rw' => $request->data_nasabah['data_alamat']['rw']?? null,
            'province' => $request->data_nasabah['data_alamat']['provinsi']?? null,
            'city' => $request->data_nasabah['data_alamat']['kota']?? null,
            'kecamatan' => $request->data_nasabah['data_alamat']['kecamatan']?? null,
            'kelurahan' => $request->data_nasabah['data_alamat']['kelurahan']?? null,
            "work_period" => $request->data_survey['lama_bekerja']?? null,
            "income_personal" => $request->data_survey['penghasilan']['pribadi']?? null,
            "income_spouse" =>  $request->data_survey['penghasilan']['pasangan']?? null,
            "income_other" =>  $request->data_survey['penghasilan']['lainnya']?? null,
            'usaha' => $request->data_survey['usaha']?? null,
            'sector' => $request->data_survey['sektor']?? null,
            "expenses" => $request->data_survey['pengeluaran']?? null,
            'survey_note' => $request->data_survey['catatan_survey']?? null,
            'coordinate' => $request->lokasi['coordinate']?? null,
            'accurate' => $request->lokasi['accurate']?? null,
            'created_by' => $request->user()->id
        ];
    
        M_CrProspect::create($data_array);
    } 

    private function createCrProspekApproval($request)
    {
        $data_approval=[
            'ID' => Uuid::uuid4()->toString(),
            'CR_PROSPECT_ID' => $request->id,
            'ONCHARGE_APPRVL' => '',
            'ONCHARGE_PERSON' => '',
            'ONCHARGE_TIME' => null,
            'ONCHARGE_DESCR' => '',
            'APPROVAL_RESULT' => '0:untouched'
        ];

        M_ProspectApproval::create($data_approval);
    } 

    private function insert_cr_vehicle($request){

        foreach ($request->jaminan_kendaraan as $result) {
            $data_array_col = [
                'ID' => Uuid::uuid4()->toString(),
                'CR_PROSPECT_ID' => $request->id,
                'HEADER_ID' => "",
                'TYPE' => $result['tipe'],
                'BRAND' => $result['merk'],
                'PRODUCTION_YEAR' => $result['tahun'],
                'COLOR' => $result['warna'],
                'ON_BEHALF' => $result['atas_nama'],
                'POLICE_NUMBER' => $result['no_polisi'],
                'CHASIS_NUMBER' => $result['no_rangka'],
                'ENGINE_NUMBER' => $result['no_mesin'],
                'BPKB_NUMBER' => $result['no_bpkb'],
                'VALUE' => $result['nilai'],
                'COLLATERAL_FLAG' => "",
                'VERSION' => 1,
                'CREATE_DATE' => Carbon::now()->format('Y-m-d'),
                'CREATE_BY' => $request->user()->id,
            ];

            M_CrGuaranteVehicle::create($data_array_col);
        }
    }

    public function update(Request $req, $id)
    {
        DB::beginTransaction();
        try {

             $req->validate([
                'visit_date' => 'date',
                'tujuan_kredit' => 'string',
                'plafond' => 'numeric',
                'tenor' => 'numeric',
                'nama' => 'string',
                'ktp' => 'numeric',
                'kk' => 'numeric',
                'tgl_lahir' => 'date',
                'alamat' => 'string',
                'hp' => 'numeric',
                'usaha' => 'string',
                'sector' => 'string',
                "penghasilan_pribadi" => "numeric",
                "penghasilan_pasangan" => "numeric",
                "penghasilan_lainnya" => "numeric",
                "pengeluaran" => "numeric"
            ]);

            $prospek = M_CrProspect::findOrFail($id);

            $req['updated_by'] = $req->user()->id;
            $req['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');

            $prospek->update($req->all());

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'Kunjungan updated successfully', 'status' => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(), 'status' => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
        }
    }

    public function destroy(Request $req,$id)
    {
        DB::beginTransaction();
        try {
            $check = M_CrProspect::findOrFail($id);

            $data = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];
            
            $check->update($data);

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'Kunjungan deleted successfully',"status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    public function uploadImage(Request $req)
    {
        try {
            DB::beginTransaction();

            $this->validate($req, [
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
                'type' => 'required|string',
                'cr_prospect_id' =>'required|string'
            ]);

            M_CrProspect::findOrFail($req->cr_prospect_id);

            $image_path = $req->file('image')->store('public/Cr_Prospect');
            $image_path = str_replace('public/', '', $image_path);

            $url= URL::to('/') . '/storage/' . $image_path;

            $data_array_attachment = [
                'ID' => Uuid::uuid4()->toString(),
                'CR_PROSPECT_ID' => $req->cr_prospect_id,
                'TYPE' => $req->type,
                'PATH' => $url ?? ''
            ];

            M_CrProspectDocument::create($data_array_attachment);

            DB::commit();
            // ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'Image upload successfully',"status" => 200,'response' => $url], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }
}

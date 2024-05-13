<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CrProspect;
use App\Models\M_CreditType;
use App\Models\M_CrProspect;
use App\Models\M_CrProspectAttachment;
use App\Models\M_CrProspectCol;
use App\Models\M_CrProspectDocument;
use App\Models\M_CrProspectPerson;
use App\Models\M_HrEmployee;
use App\Models\M_ProspectApproval;
use App\Models\M_SlikApproval;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        // $attachmentData = M_CrProspectAttachment::orderBy('type','asc')->where('cr_prospect_id',$data->id )->get();

        $arrayList = [
            'id' => $data->id,
            'ao_id' => $data->ao_id,
            'data_ao' =>  [
                [
                    'id_ao' => $request->user()->id,
                    'nama_ao' => $request->user()->username,
                ]
            ],
            'visit_date' => date('d-m-Y',strtotime($data->visit_date)),
            'tujuan_kredit' => $data->tujuan_kredit,
            'plafond' => 'IDR '.number_format($data->plafond,0,",","."),
            'tenor' => "$data->tenor",
            'nama' => $data->nama,
            'ktp' => $data->ktp,
            'kk' => $data->kk,
            'tgl_lahir' => date('d-m-Y',strtotime($data->tgl_lahir)),
            'alamat' => $data->alamat,
            'hp' => $data->hp,
            'usaha' => $data->usaha,
            'sector' => $data->sector,
            'coordinate' => $data->coordinate,
            'accurate' => $data->accurate,
            'slik' => $data->slik == "1" ? 'ya':"tidak",
            'ktp_attachment' => [],
            'kk_attachment' => [],
            'buku_nikah' => [],
            'prospek_jaminan' => [],
            'prospek_penjamin' => [],
            'prospek_attachment' => [],
            'slik_approval' => ''
        ];

        // foreach ($attachmentData as $list) {
        //     if(strtolower($list->type) == 'ktp'){
        //         $arrayList['ktp_attachment'] = URL::to('/').'/storage/'.$list->attachment_path;
        //     }
        // }

        // foreach ($attachmentData as $list) {
        //     if(strtolower($list->type) == 'kk'){
        //         $arrayList['kk_attachment'] = URL::to('/').'/storage/'.$list->attachment_path;;
        //     }
        // }

        // foreach ($attachmentData as $list) {
        //     if(strtolower($list->type) == 'buku nikah'){
        //         $arrayList['buku_nikah'] = URL::to('/').'/storage/'.$list->attachment_path;;
        //     }
        // }

        // foreach ($attachmentData as $list) {
        //     if(str_contains($list->type, 'attachment')){
        //         $arrayList['prospek_attachment'][] = [
        //             'id' => $list->id,
        //             'type' => $list->type,
        //             'path' => URL::to('/').'/storage/'.$list->attachment_path,
        //         ];
        //     }
        // }
        
        return $arrayList;
    }
    
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'id' => 'required|string|unique:cr_prospect',
                'visit_date' => 'required|date',
                'tujuan_kredit' => 'required|string',
                'plafond' => 'required|numeric',
                'tenor' => 'required|numeric',
                'nama' => 'required|string',
                'ktp' => 'required|numeric',
                'kk' => 'required|numeric',
                'tgl_lahir' => 'required|date',
                'alamat' => 'required|string',
                'hp' => 'required|numeric',
                'usaha' => 'required|string',
                'sector' => 'required|string',
                'collateral_value' => 'numeric',
                "nama_ibu" => "required",
                "npwp" => "required",
                "pendidikan_terakhir" => "required",
                "lama_bekerja" => "required",
                "jumlah_tanggungan" => "required",
                "pendapatan_pribadi" => "required|numeric",
                "penghasilan_pasangan" => "required|numeric",
                "penghasilan_lainnya" => "required|numeric",
                "pengeluaran" => "required|numeric"
            ]);
    
            $data_array = [
                'id' => $request->id,
                'ao_id' => $request->user()->id,
                'visit_date' => $request->visit_date,
                'tujuan_kredit' => $request->tujuan_kredit,
                'plafond' => $request->plafond,
                'tenor' => $request->tenor,
                'nama' => $request->nama,
                'ktp' => $request->ktp,
                'kk' => $request->kk,
                'tgl_lahir' => $request->tgl_lahir,
                'alamat' => $request->alamat,
                'hp' => $request->hp,
                'usaha' => $request->usaha,
                'sector' => $request->sector,
                'coordinate' => $request->coordinate,
                'accurate' => $request->accurate,
                'collateral_value' => $request->accurate,
                "mother_name" => $request->nama_ibu,
                "tin_number" => $request->npwp,
                "title" => $request->pendidikan_terakhir,
                "work_period" => $request->lama_bekerja,
                "dependants" => $request->jumlah_tanggungan,
                "income_personal" => $request->pendapatan_pribadi,
                "income_spouse" => $request->penghasilan_pasangan,
                "income_other" => $request->penghasilan_lainnya,
                "expenses" => $request->pengeluaran,
                'created_by' => $request->user()->id
            ];
        
            M_CrProspect::create($data_array);

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
                'slik' => 'numeric',
                'collateral_value' => 'numeric'
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

    // public function uploadImage(Request $req)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $this->validate($req, [
    //             'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
    //             'type' => 'required|string',
    //             'cr_prospect_id' =>'required|string'
    //         ]);

    //         M_CrProspect::findOrFail($req->cr_prospect_id);

    //         $image_path = $req->file('image')->store('public/Cr_Prospect');
    //         $image_path = str_replace('public/', '', $image_path);

    //         $url= URL::to('/') . '/storage/' . $image_path;

    //         $data_array_attachment = [
    //             'id' => Uuid::uuid4()->toString(),
    //             'cr_prospect_id' => $req->cr_prospect_id,
    //             'type' => $req->type,
    //             'attachment_path' => $url ?? ''
    //         ];

    //         M_CrProspectAttachment::create($data_array_attachment);

    //         DB::commit();
    //         ActivityLogger::logActivity($req,"Success",200);
    //         return response()->json(['message' => 'Image upload successfully',"status" => 200,'response' => $url], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req, 'Cr Prospect Id Not Found', 404);
    //         return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req,$e->getMessage(),409);
    //         return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($req,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
    //     } 
    // }
}

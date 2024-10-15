<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_CrProspect;
use App\Models\M_CrGuaranteVehicle;
use App\Models\M_CrSurvey;
use App\Models\M_CrSurveyDocument;
use App\Models\M_SurveyApproval;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class CrSurveyController extends Controller
{

    private $CrSurvey;
    private $uuid;
    private $timeNow;

    public function __construct(M_CrSurvey $CrSurvey)
    {
        $this->CrSurvey = $CrSurvey;
        $this->uuid = Uuid::uuid7()->toString();
        $this->timeNow = Carbon::now();
    }

    public function index(Request $req){
        try {
            $mcf_id = $req->user()->id;
            $data =  $this->CrSurvey->show_mcf($mcf_id);
            $dto = R_CrProspect::collection($data);
    
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
        } catch (QueryException $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function showAdmins(Request $req){
        try {
            $get_branch = $req->user()->branch_id;
            $data =  $this->CrSurvey->show_admin($get_branch);
            $dto = R_CrProspect::collection($data);
    
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
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
            $check = $this->CrSurvey->where('id',$id)->whereNull('deleted_at')->firstOrFail();

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => self::resourceDetail($check)], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function resourceDetail($data)
    {
        $survey_id = $data->id;
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID',$survey_id)->get(); 
        $approval_detail = M_SurveyApproval::where('CR_SURVEY_ID',$survey_id)->first();
        
        $arrayList = [
            'id' => $survey_id,
            'data_order' =>[
                'tujuan_kredit' => $data->tujuan_kredit,
                'plafond' => (int) $data->plafond,
                'tenor' => strval($data->tenor), 
                'kategory' => $data->category,
                'jenis_angsuran' => $data->jenis_angsuran 
            ],
            'data_nasabah' => [
                'nama' => $data->nama,
                'tgl_lahir' => is_null($data->tgl_lahir) ? null : date('Y-m-d',strtotime($data->tgl_lahir)),
                'no_hp' => $data->hp,
                'no_ktp' => $data->ktp,
                'no_kk' => $data->kk,
                'alamat' => $data->alamat,
                'rt' => $data->rt,
                'rw' => $data->rw,
                'provinsi' => $data->province,
                'kota' => $data->city,
                'kelurahan' => $data->kelurahan,
                'kecamatan' => $data->kecamatan,
                'kode_pos' => $data->zip_code
            ], 
            'data_survey' =>[
                'usaha' => $data->usaha,
                'sektor' => $data->sector,
                'lama_bekerja' => $data->work_period,
                'pengeluaran' => (int) $data->expenses,
                'penghasilan_pribadi' => (int) $data->income_personal,
                'penghasilan_pasangan' => (int) $data->income_spouse,
                'penghasilan_lainnya' => (int) $data->income_other,
                'tgl_survey' => is_null($data->visit_date) ? null: date('Y-m-d',strtotime($data->visit_date)),
                'catatan_survey' => $data->survey_note,
            ], 
            'jaminan_kendaraan' => [],
            'prospect_approval' => [
                'flag_approval' => $approval_detail->ONCHARGE_APPRVL,
                'keterangan' => $approval_detail->ONCHARGE_DESCR,
                'status' => $approval_detail->APPROVAL_RESULT
            ],
            "dokumen_indentitas" => self::attachment($survey_id, "'ktp', 'kartu keluarga', 'ktp pasangan'"),
            // "dokumen_jaminan" => self::attachment($survey_id, ['no rangka', 'no mesin', 'stnk', 'tampak depan', 'tampak belakang', 'tampak kanan', 'tampak kiri'])??null,
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($survey_id, ['dokumen pendukung'])??null,
        ];

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan_kendaraan'][] = [
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
                "nilai" => (int) $list->VALUE
            ];    
        }
        
        
        return $arrayList;
    }

    public function attachment($survey_id, $data){
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, CREATED_AT) IN (
                    SELECT TYPE, MAX(CREATED_AT)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                    GROUP BY TYPE
                )
                ORDER BY CREATED_AT DESC"
        );
    
        return $documents;        
    }
    
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'id' => 'required|string|unique:cr_survey',
            ]);

            $check_id_approval =  M_SurveyApproval::where('CR_SURVEY_ID', $request->id)->get();

            if ($check_id_approval->isNotEmpty()) {
                throw new Exception("Id Approval Is Exist", 409);
            }

            self::createCrSurvey($request);
            self::createCrProspekApproval($request);

            if (collect($request->jaminan_kendaraan)->isNotEmpty()) {
                self::insert_cr_vehicle($request);
            }
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'created successfully',"status" => 200], 200);
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

    private function createCrSurvey($request)
    {
        $data_array = [
            'id' => $request->id,
            'branch_id' => $request->user()->branch_id,
            'visit_date' => isset($request->data_survey['tgl_survey']) && !empty($request->data_survey['tgl_survey'])?$request->data_survey['tgl_survey']:null,
            'tujuan_kredit' => $request->order['tujuan_kredit']?? null,
            'plafond' => $request->order['plafond']?? null,
            'tenor' => $request->order['tenor']?? null,
            'category' => $request->order['category']?? null,
            'jenis_angsuran' => $request->order['jenis_angsuran']?? null,
            'nama' => $request->data_nasabah['nama']?? null,
            'tgl_lahir' => $request->data_nasabah['tgl_lahir']?? null,
            'ktp' => $request->data_nasabah['no_ktp']?? null,
            'kk' => $request->data_nasabah['no_kk']?? null,
            'hp' => $request->data_nasabah['no_hp']?? null,
            'alamat' => $request->data_nasabah['alamat']?? null,
            'rt' => $request->data_nasabah['rt']?? null,
            'rw' => $request->data_nasabah['rw']?? null,
            'province' => $request->data_nasabah['provinsi']?? null,
            'city' => $request->data_nasabah['kota']?? null,
            'kecamatan' => $request->data_nasabah['kecamatan']?? null,
            'kelurahan' => $request->data_nasabah['kelurahan']?? null,
            "work_period" => $request->data_survey['lama_bekerja']?? null,
            "income_personal" => $request->data_survey['penghasilan']['pribadi']?? null,
            "income_spouse" =>  $request->data_survey['penghasilan']['pasangan']?? null,
            "income_other" =>  $request->data_survey['penghasilan']['lainnya']?? null,
            'usaha' => $request->data_survey['usaha']?? null,
            'sector' => $request->data_survey['sektor']?? null,
            "expenses" => $request->data_survey['pengeluaran']?? null,
            'survey_note' => $request->data_survey['catatan_survey']?? null,
            'created_by' => $request->user()->id
        ];

        M_CrSurvey::create($data_array);

    } 

    private function createCrProspekApproval($request)
    {
        $approvalLog = new ApprovalLog();
        $result = '1:waiting fpk';
        $data_approval=[
            'ID' => $this->uuid,
            'CR_SURVEY_ID' => $request->id,
            'APPROVAL_RESULT' => $result
        ];

        $approval = M_SurveyApproval::create($data_approval);
        $approvalLog->surveyApprovalLog($request->user()->id, $approval->ID, $result);
    } 

    private function insert_cr_vehicle($request){

        foreach ($request->jaminan_kendaraan as $result) {
            $data_array_col = [
                'ID' => $this->uuid,
                'CR_SURVEY_ID' => $request->id,
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
                'STNK_NUMBER' => $result['no_stnk'],
                'VALUE' => $result['nilai'],
                'COLLATERAL_FLAG' => "",
                'VERSION' => 1,
                'CREATE_DATE' => $this->timeNow,
                'CREATE_BY' => $request->user()->id,
            ];

            M_CrGuaranteVehicle::create($data_array_col);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $data_prospect = [
                'tujuan_kredit' => $request->order['tujuan_kredit']?? null,
                'plafond' => $request->order['plafond']?? null,
                'tenor' => $request->order['tenor']?? null,
                'category' => $request->order['kategory']?? null,
                'jenis_angsuran' => $request->order['jenis_angsuran']?? null,
                'nama' => $request->data_nasabah['nama']?? null,
                'tgl_lahir' => date('Y-m-d', strtotime($request->data_nasabah['tgl_lahir']))?? null,
                'ktp' => $request->data_nasabah['no_ktp']?? null,
                'hp' => $request->data_nasabah['no_hp']?? null,
                'kk' => $request->data_nasabah['no_kk']?? null,
                'alamat' => $request->data_nasabah['alamat']?? null,
                'rt' => $request->data_nasabah['rt']?? null,
                'rw' => $request->data_nasabah['rw']?? null,
                'province' => $request->data_nasabah['provinsi']?? null,
                'city' => $request->data_nasabah['kota']?? null,
                'kecamatan' => $request->data_nasabah['kecamatan']?? null,
                'kelurahan' => $request->data_nasabah['kelurahan']?? null,
                'usaha' => $request->data_survey['usaha'] ?? null,
                'sector' => $request->data_survey['sektor'] ?? null,
                "work_period" => $request->data_survey['lama_bekerja']?? null,
                "expenses" => $request->data_survey['pengeluaran'] ?? null,
                "income_personal" => $request->data_survey['penghasilan_pribadi']?? null,
                "income_spouse" =>  $request->data_survey['penghasilan_pasangan']?? null,
                "income_other" =>  $request->data_survey['penghasilan_lainnya']?? null,
                'visit_date' => is_null($request->data_survey['tgl_survey']) ? null : date('Y-m-d', strtotime($request->data_survey['tgl_survey'])),
                'survey_note' => $request->data_survey['catatan_survey']?? null,
                'updated_by' => $request->user()->id,
                'updated_at' => $this->timeNow
            ];

            $prospek_check = M_CrSurvey::where('id',$id)->whereNull('deleted_at')->first();

            if (!$prospek_check) {
                throw new Exception("Cr Survey Id Not Found",404);
            }

            $prospek_check->update($data_prospect);

            compareData(M_CrSurvey::class,$id,$data_prospect,$request);

            if (collect($request->jaminan_kendaraan)->isNotEmpty()) {
                foreach ($request->jaminan_kendaraan as $result) {

                    $jaminan_check = M_CrGuaranteVehicle::where(['ID' => $result['id'],'CR_SURVEY_ID' =>$id])->whereNull('DELETED_AT')->first();

                    if (!$jaminan_check) {
                        throw new Exception("Id Jaminan Not Found",404);
                    }

                    $data_jaminan = [
                        'HEADER_ID' => "",
                        'TYPE' => $result['tipe']??null,
                        'BRAND' => $result['merk']??null,
                        'PRODUCTION_YEAR' => $result['tahun']??null,
                        'COLOR' => $result['warna']??null,
                        'ON_BEHALF' => $result['atas_nama']??null,
                        'POLICE_NUMBER' => $result['no_polisi']??null,
                        'CHASIS_NUMBER' => $result['no_rangka']??null,
                        'ENGINE_NUMBER' => $result['no_mesin']??null,
                        'BPKB_NUMBER' => $result['no_bpkb']??null,
                        'STNK_NUMBER' => $result['no_stnk']??null,
                        'VALUE' => $result['nilai']??null,
                        'COLLATERAL_FLAG' => "",
                        'VERSION' => 1,
                        'MOD_DATE' => $this->timeNow,
                        'MOD_BY' => $request->user()->id,
                    ];
        
                    compareData(M_CrGuaranteVehicle::class,$result['id'],$data_jaminan,$request);

                   $jaminan_check->update($data_jaminan);
                }
            }

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'updated successfully', 'status' => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Cr Prospect Id Not Found', 404);
            return response()->json(['message' => 'Cr Prospect Id Not Found', "status" => 404], 404);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(), 'status' => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
        }
    }

    public function destroy(Request $req,$id)
    {
        DB::beginTransaction();
        try {
            $check = M_CrSurvey::findOrFail($id);

            $data = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => $this->timeNow
            ];
            
            $check->update($data);

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'deleted successfully',"status" => 200], 200);
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
        DB::beginTransaction();
        try {

            $this->validate($req, [
                'image' => 'image|mimes:jpg,png,jpeg,gif,svg',
                'type' => 'string',
                'cr_prospect_id' =>'string'
            ]);

            if ($req->hasFile('image')) {
                $image_path = $req->file('image')->store('public/Cr_Survey');
                $image_path = str_replace('public/', '', $image_path);

                $url = URL::to('/') . '/storage/' . $image_path;

                $data_array_attachment = [
                    'ID' => Uuid::uuid4()->toString(),
                    'CR_SURVEY_ID' => $req->cr_prospect_id,
                    'TYPE' => $req->type,
                    'PATH' => $url ?? ''
                ];

                M_CrSurveyDocument::create($data_array_attachment);

                DB::commit();
                return response()->json(['message' => 'Image upload successfully', "status" => 200, 'response' => $url], 200);
            } else {
                DB::rollback();
                ActivityLogger::logActivity($req, 'No image file provided', 400);
                return response()->json(['message' => 'No image file provided', "status" => 400], 400);
            }
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

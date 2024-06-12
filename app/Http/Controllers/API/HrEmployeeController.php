<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Employee;
use App\Http\Resources\R_EmployeeDetail;
use App\Models\M_Branch;
use App\Models\M_HrEmployee;
use App\Models\M_HrEmployeeDocument;
use App\Models\M_JabaranAccessMenu;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class HrEmployeeController extends Controller
{

    private $current_time;

    public function __construct()
    {
        $this->current_time = Carbon::now()->format('Y-m-d H:i:s');
    }

    public function index(Request $request)
    {
        try {
            $data =  M_HrEmployee::all();
            $dto = R_Employee::collection($data);

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            $check = M_HrEmployee::where('ID',$id)->firstOrFail();
            $dto =  new R_EmployeeDetail($check);

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function nikCounter()
    {
        $checkMax = M_HrEmployee::max('NIK');

        $currentDate = Carbon::now();
        $year = substr($currentDate->format('Y'), -2);
        $month = $currentDate->format('m');
        $lastSequence = (int) substr($checkMax, 4, 3);
        $lastSequence++;

        $generateCode = $year . $month . sprintf("%03s", $lastSequence);

        return $generateCode;
    }

    private function _validation($request){
        $validation = $request->validate([
            'branch_id' => 'required|string',
            'tgl_lahir' => 'date',
            'tgl_keluar' => 'date',
            'email' => 'email',
            'kode_pos_ktp' => 'numeric',
            'kode_pos' => 'numeric',
        ]);

        return $validation;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            self::_validation($request);

            $generate_nik =  self::nikCounter();

            if(!empty($request->spv_id)){
                $check_spv = M_HrEmployee::where('ID',$request->spv_id)->first();

                if (!$check_spv) {
                    throw new Exception("Id SPV Not Found",404);
                }
            }

            $check_branch = M_Branch::where('ID',$request->branch_id)->first();

            if (!$check_branch) {
                throw new Exception("Id Branch Not Found",404);
            }

            $data =[
                'ID' => $request->employee_id,
                'NIK' => !empty($request->nik)?$request->nik: $generate_nik,
                'NAMA' => $request->nama,
                'AO_CODE' => "",
                'BRANCH_ID' => $request->branch_id,
                'JABATAN' => $request->jabatan,
                'BLOOD_TYPE' => $request->blood_type,
                'GENDER' => $request->gender,
                'PENDIDIKAN' => $request->pendidikan,
                'UNIVERSITAS' => $request->universitas,
                'JURUSAN' => $request->jurusan,
                'IPK' => $request->ipk,
                'IBU_KANDUNG' => $request->ibu_kandung,
                'STATUS_KARYAWAN' => $request->status_karyawan,
                'NAMA_PASANGAN' => $request->nama_pasangan,
                'TANGGUNGAN' => $request->tanggungan,
                'NO_KTP' => $request->no_ktp,
                'NAMA_KTP' => $request->nama_ktp,
                'ADDRESS_KTP' => $request->alamat_ktp,
                'RT_KTP' => $request->rt_ktp,
                'RW_KTP' => $request->rw_ktp,
                'PROVINCE_KTP' => $request->provinsi_ktp,
                'CITY_KTP' => $request->kota_ktp,
                'KELURAHAN_KTP' => $request->kelurahan_ktp,
                'KECAMATAN_KTP' => $request->kecamatan_ktp,
                'ZIP_CODE_KTP' => $request->kode_pos_ktp,
                'ADDRESS' => $request->alamat_tinggal,
                'RT' => $request->rt,
                'RW' => $request->rw,
                'PROVINCE' => $request->provinsi,
                'CITY' => $request->kota,
                'KELURAHAN' => $request->kelurahan,
                'KECAMATAN' => $request->kecamatan,
                'ZIP_CODE' => $request->kode_pos,
                'TGL_LAHIR' => $request->tgl_lahir,
                'TEMPAT_LAHIR' => $request->tempat_lahir,
                'AGAMA' => $request->agama,
                'TELP' => $request->telp,
                'HP' => $request->hp,
                'NO_REK_CF' => $request->no_rek_cf,
                'NO_REK_TF' => $request->no_rek_tf,
                'EMAIL' => $request->email,
                'NPWP' => $request->npwp,
                'SUMBER_LOKER' => $request->sumber_loker,
                'KET_LOKER' => $request->ket_loker,
                'INTERVIEW' => $request->interview,
                'TGL_KELUAR' => $request->tgl_keluar,
                'ALASAN_KELUAR' => $request->alasan_keluar,
                'CUTI' => "",
                'PHOTO_LOC' => "",
                'SPV' => $request->spv_id,
                'STATUS_MST' => $request->status_mst,
                'CREATED_AT' => $this->current_time,
                'CREATED_BY' =>  $request->user()->id
            ];

            $idEmployee = M_HrEmployee::create($data);

            $data_array = [
                'username' => $generate_nik,
                'employee_id' => $idEmployee->ID,
                'email' => $generate_nik.'@gmail.com',
                'password' => bcrypt($generate_nik),
                'status' => 'Active',
                'created_by' => $request->user()->id
            ];

            $idUser = User::create($data_array);

            $getMenu = M_JabaranAccessMenu::where('jabatan',$request->jabatan)->get();

            foreach ($getMenu as $list) {
                $data_menu = [
                    'id' => Uuid::uuid7()->toString(),
                    'master_menu_id' => $list['master_menu_id'],
                    'users_id' => $idUser->id,
                    'created_by' => $request->user()->id
                ];

                M_MasterUserAccessMenu::create($data_menu);
            }
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Karyawan created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'v', 404);
            return response()->json(['message' => 'Spv Id Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            self::_validation($request);

            $check = M_HrEmployee::findOrFail($id);

            if($check->JABATAN != $request->jabatan){
                $getUserId = User::where('employee_id',$id)->first();
                $getMenu = M_JabaranAccessMenu::where('jabatan',$request->jabatan)->get();

                M_MasterUserAccessMenu::where('users_id', $getUserId->id)->delete();

                foreach ($getMenu as $list) {
                    $data_menu = [
                        'id' => Uuid::uuid7()->toString(),
                        'master_menu_id' => $list['master_menu_id'],
                        'users_id' => $getUserId->id,
                        'created_by' => $request->user()->id
                    ];
    
                    M_MasterUserAccessMenu::create($data_menu);
                }
            }

            $data = [
                'NAMA' => $request->nama,
                'BRANCH_ID' => $request->branch_id,
                'JABATAN' => $request->jabatan,
                'BLOOD_TYPE' => $request->blood_type,
                'GENDER' => $request->gender,
                'PENDIDIKAN' => $request->pendidikan,
                'UNIVERSITAS' => $request->universitas,
                'JURUSAN' => $request->jurusan,
                'IPK' => $request->ipk,
                'IBU_KANDUNG' => $request->ibu_kandung,
                'STATUS_KARYAWAN' => $request->status_karyawan,
                'NAMA_PASANGAN' => $request->nama_pasangan,
                'TANGGUNGAN' => $request->tanggungan,
                'NO_KTP' => $request->no_ktp,
                'NAMA_KTP' => $request->nama_ktp,
                'ADDRESS_KTP' => $request->alamat_ktp,
                'RT_KTP' => $request->rt_ktp,
                'RW_KTP' => $request->rw_ktp,
                'PROVINCE_KTP' => $request->provinsi_ktp,
                'CITY_KTP' => $request->kota_ktp,
                'KELURAHAN_KTP' => $request->kelurahan_ktp,
                'KECAMATAN_KTP' => $request->kecamatan_ktp,
                'ZIP_CODE_KTP' => $request->kode_pos_ktp,
                'ADDRESS' => $request->alamat_tinggal,
                'RT' => $request->rt,
                'RW' => $request->rw,
                'PROVINCE' => $request->provinsi,
                'CITY' => $request->kota,
                'KELURAHAN' => $request->kelurahan,
                'KECAMATAN' => $request->kecamatan,
                'ZIP_CODE' => $request->kode_pos,
                'TGL_LAHIR' => $request->tgl_lahir,
                'TEMPAT_LAHIR' => $request->tempat_lahir,
                'AGAMA' => $request->agama,
                'TELP' => $request->telp,
                'HP' => $request->hp,
                'NO_REK_CF' => $request->no_rek_cf,
                'NO_REK_TF' => $request->no_rek_tf,
                'EMAIL' => $request->email,
                'NPWP' => $request->npwp,
                'SUMBER_LOKER' => $request->sumber_loker,
                'KET_LOKER' => $request->ket_loker,
                'INTERVIEW' => $request->interview,
                'TGL_KELUAR' => $request->tgl_keluar,
                'ALASAN_KELUAR' => $request->alasan_keluar,
                'CUTI' => "",
                'PHOTO_LOC' => "",
                'SPV' => $request->spv_id,
                'STATUS_MST' => $request->status_mst,
                'UPDATED_AT' => $this->current_time,
                'UPDATED_BY' =>  $request->user()->id
            ];

            $check->update($data);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Karyawan updated successfully', "status" => $check->JABATAN], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroy(Request $req, $id)
    {
        DB::beginTransaction();
        try {

            $check = M_HrEmployee::findOrFail($id);

            $update = [
                'status_mst' => 'inactive',
                'deleted_by' => $req->user()->id,
                'deleted_at' => $this->current_time
            ];

            $data = array_change_key_case($update, CASE_UPPER);

            $check->update($data);

            DB::commit();
            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'Karyawan deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Data Not Found', 404);
            return response()->json(['message' => 'Hr Employee Id Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function uploadImage(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->validate($request, [
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
                'type' => 'required|string',
                'employee_id' => 'required|string'
            ]);

            $employee =  $request->employee_id;
    
            $folderPath = 'public/Employee_Image/' . $employee;

            if (!Storage::exists($folderPath)) {
                Storage::makeDirectory($folderPath);
            }

            $image_path = $request->file('image')->store($folderPath);
            $image_path = str_replace('public/', '', $image_path);

            $url = URL::to('/') . '/storage/' . $image_path;

            $data_array_attachment = [
                'ID' => Uuid::uuid4()->toString(),
                'EMPLOYEE_ID' => $employee,
                'TYPE' => $request->type,
                'PATH' => $url ?? ''
            ];

            M_HrEmployeeDocument::create($data_array_attachment);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Image upload successfully', "status" => 200], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

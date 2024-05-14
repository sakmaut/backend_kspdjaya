<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Employee;
use App\Models\M_HrEmployee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $check], 200);
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

            $uuid = Uuid::uuid4()->toString();
            $generate_nik =  self::nikCounter();

            if(!empty($request->spv_id)){
                M_HrEmployee::findOrFail($request->spv_id);
            }

            $data =[
                'ID' => $uuid,
                'NIK' => !empty($request->nik)?$request->nik: $generate_nik,
                'NAMA' => $request->nama,
                'AO_CODE' => "",
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

            M_HrEmployee::create($data);

            $data_array = [
                'username' => $generate_nik,
                'employee_id' => $uuid,
                'email' => $generate_nik.'@gmail.com',
                'password' => bcrypt($generate_nik),
                'status' => 'Active',
                'created_by' => $request->user()->id
            ];

            User::create($data_array);
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'Karyawan created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Spv Id Not Found', 404);
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

            $data = [
                'NAMA' => $request->nama,
                'NIK' => $request->nik,
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
            return response()->json(['message' => 'Karyawan updated successfully', "status" => 200], 200);
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
}

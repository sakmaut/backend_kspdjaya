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
            $data = M_HrEmployee::with('user')->get();
            $dto = self::resourceDetail($data);

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
            $data = M_HrEmployee::with('user')->where('ID',$id)->get();
            $dto =  self::resourceDetail($data);

            if ($data->isEmpty()) {
                ActivityLogger::logActivity($req,'Data Not Found',404);
                throw new Exception("Data Not Found",404);
            }

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $dto], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    private function resourceDetail($data){

        $employeeDTOs= [];
        foreach ($data as $result) {
            $branch = M_Branch::find($result->BRANCH_ID);
            $user = $result->user;
    
            $employeeDTO = [
                'id' => $result->ID,
                'username' => $user ? $user->username : null,
                'nama' => $result->NAMA,
                'cabang_id' => $branch->ID ?? null,
                'cabang_nama' => $branch->NAME ?? null,
                'jabatan' => $result->JABATAN,
                'gender' => $result->GENDER,
                'no_hp' => $result->HP,
                'status' => $result->STATUS_MST,
                'photo_personal' => M_HrEmployeeDocument::attachment($result->ID, 'personal'),
            ];
        
            $employeeDTOs = array_merge($employeeDTOs, $employeeDTO);
        }
    
        return $employeeDTOs;
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
            'cabang' => 'required|string'
        ]);

        return $validation;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            self::_validation($request);

            $check_branch = M_Branch::where('ID',$request->cabang)->first();

            if (!$check_branch) {
                throw new Exception("Id Branch Not Found",404);
            }

            $data =[
                'ID' => Uuid::uuid7()->toString(),
                'NAMA' => $request->nama,
                'BRANCH_ID' => $request->cabang,
                'JABATAN' => $request->jabatan,
                'GENDER' => $request->gender,
                'HP' => $request->no_hp,
                'STATUS_MST' => $request->status,
                'CREATED_AT' => $this->current_time,
                'CREATED_BY' =>  $request->user()->id
            ];

            $idEmployee = M_HrEmployee::create($data);

            $data_array = [
                'username' => $request->username,
                'employee_id' => $idEmployee->ID,
                'email' => $request->username.'@gmail.com',
                'password' => bcrypt($request->password),
                'status' => 'active',
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
                'BRANCH_ID' => $request->cabang,
                'JABATAN' => $request->jabatan,
                'GENDER' => $request->gender,
                'HP' => $request->no_hp,
                'STATUS_MST' => $request->status,
                'UPDATED_AT' => $this->current_time,
                'UPDATED_BY' =>  $request->user()->id
            ];

            $check->update($data);

            $user = User::where('employee_id',$id)->first();

            if (!$user) {
                throw new Exception("User Id Not Found",404);
            }

            $data_user = [
                'updated_by' => $request->user()->id,
                'updated_at' => $this->current_time
            ];
            
            if (isset($request->username)) {
                $data_user['username'] = $request->username;
            }

            if (isset($request->password)) {
                $data_user['password'] = bcrypt($request->password);
            }

            if (isset($request->status)) {
                $data_user['status'] = $request->status;
            }
    
            $user->update($data_user);
    
            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Updated successfully', "status" => $check->JABATAN], 200);
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

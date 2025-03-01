<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_User;
use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use App\Models\M_HrRolling;
use App\Models\M_JabatanAccessMenu;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class UsersController extends Controller
{
    private $current_time;

    public function __construct()
    {
        $this->current_time = Carbon::now()->format('Y-m-d H:i:s');
    }

    public function index(Request $req)
    {
        try {
            $data = User::where('status', 'LIKE', '%active%')->get();
            $dto = R_User::collection($data);

            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $dto], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function show(Request $req, $id)
    {
        try {
            $check = User::where('id', $id)->firstOrFail();
            $dto = new R_User($check);

            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'OK', "status" => 200, 'response' => $dto], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function _validate($request)
    {

        $validation = $request->validate([
            'cabang_id' => 'required|string'
        ]);

        return $validation;
    }

    public function storeArray(Request $request)
    {
        DB::beginTransaction();
        try {

            foreach ($request->all as $list) {
                $data_array = [
                    'username' => $list['username'],
                    'email' => $list['username'] . '@gmail.com',
                    'password' => bcrypt($list['username']),
                    'fullname' => $list['username'],
                    'branch_id' => $list['branch_id'],
                    'position' => $list['position'],
                    'no_ktp' => '',
                    'alamat' => '',
                    'gender' => '',
                    'mobile_number' => '',
                    'status' => 'Active',
                    'created_by' => 'SYSTEM'
                ];

                User::create($data_array);
            }

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'created successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Data Not Found', 404);
            return response()->json(['message' => 'Hr Employee Id Not Found', "status" => 404], 404);
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

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->_validate($request);

            $users = User::where('username', $request->username)->first();

            if ($users) {
                throw new Exception("Username Is Exist", 404);
            }

            $check_branch = M_Branch::where('ID', $request->cabang_id)->first();

            if (!$check_branch) {
                throw new Exception("Id Branch Not Found", 404);
            }

            $data_array = [
                'username' => $request->username ?? '',
                'email' => $request->username . '@gmail.com',
                'password' => !empty($request->password) ? bcrypt($request->password) : bcrypt($request->username),
                'fullname' => $request->nama ?? '',
                'branch_id' => $request->cabang_id ?? '',
                'position' => $request->jabatan ?? '',
                'no_ktp' => $request->no_ktp ?? '',
                'alamat' => $request->alamat ?? '',
                'gender' => $request->gender ?? '',
                'mobile_number' => $request->no_hp ?? '',
                'status' => $request->status == '' ? 'active' : $request->status,
                'created_by' => $request->user()->id
            ];

            $userID = User::create($data_array);

            $getMenu = M_JabatanAccessMenu::where('jabatan', $request->jabatan)->get();

            if (!$getMenu->isEmpty()) {
                foreach ($getMenu as $list) {
                    $data_menu = [
                        'id' => Uuid::uuid7()->toString(),
                        'master_menu_id' => $list['master_menu_id'],
                        'users_id' => $userID->id,
                        'created_by' => $request->user()->id
                    ];

                    M_MasterUserAccessMenu::create($data_menu);
                }
            }

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'created successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, 'Data Not Found', 404);
            return response()->json(['message' => 'Hr Employee Id Not Found', "status" => 404], 404);
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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->_validate($request);

            $users = User::findOrFail($id);

            if (!$users) {
                throw new Exception("Username Is Exist", 404);
            }

            $getJabatan = M_JabatanAccessMenu::where('jabatan', $request->jabatan)->first();

            $data_user = [
                'username' => $request->username ?? '',
                'fullname' => $request->nama ?? '',
                'branch_id' => $request->cabang_id ?? '',
                'position' => $getJabatan->jabatan ?? $request->jabatan ?? '',
                'no_ktp' => $request->no_ktp ?? '',
                'alamat' => $request->alamat ?? '',
                'gender' => $request->gender ?? '',
                'mobile_number' => $request->no_hp ?? '',
                'status' => $request->status ?? '',
                'updated_by' => $request->user()->id ?? '',
                'updated_at' => $this->current_time ?? ''
            ];

            if (isset($request->password) && !empty($request->password)) {
                $data_user['password'] = bcrypt($request->password);
            }

            $users->update($data_user);

            DB::commit();
            return response()->json(['message' => 'User updated successfully', "status" => 200], 200);
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

            $users = User::findOrFail($id);

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => $this->current_time
            ];

            $users->update($update);

            DB::commit();
            ActivityLogger::logActivity($req, "Success", 200);
            return response()->json(['message' => 'Users deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req, 'Data Not Found', 404);
            return response()->json(['message' => 'Data Not Found', "status" => 404], 404);
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
                'type' => 'required|string'
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
                'ID' => Uuid::uuid7()->toString(),
                'USERS_ID' => '',
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

    public function changePassword(Request $request)
    {
        DB::beginTransaction();
        try {
            // Autentikasi pengguna
            if (!Auth::guard('web')->attempt($request->only('username', 'password'))) {
                return response()->json(['message' => 'Invalid Credential'], 401);
            }

            // Perbarui kata sandi
            $request->user()->update(['password' => bcrypt($request->new_password)]);
            $request->user()->tokens()->delete();

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'Kata sandi berhasil diperbarui'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function resetPassword(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'username' => 'required|string'
            ]);

            $user_query = User::where('username', $request->username)->first();

            if (!$user_query) {
                throw new Exception('User Not Found');
            }

            $user_query->update(['password' => bcrypt($request->username)]);

            DB::commit();
            return response()->json(['message' => 'reset password successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

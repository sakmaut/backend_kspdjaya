<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Users\UserRepositories;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class UsersController extends Controller
{
    private $current_time;
    protected $usersRepository;
    protected $log;

    public function __construct(UserRepositories $usersRepository,ExceptionHandling $log)
    {
        $this->current_time = Carbon::now()->format('Y-m-d H:i:s');
        $this->usersRepository = $usersRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getActiveUsers = $this->usersRepository->getActiveUsers();

            $dto = R_User::collection($getActiveUsers);

            return response()->json(['message' => 'OK','response' => $dto], 200);
        } catch (\Exception $e) {
            $this->log->logError($e,$request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

    public function show(Request $request,$id)
    {
        try {
            $userById = $this->usersRepository->findUserByid($id);

            if(!$userById){
                throw new Exception("Users Not Found", 404);
            }

            $dto = new R_User($userById);

            return response()->json(['message' => 'OK','response' => $dto], 200);
        } catch (\Exception $e) {
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

    // public function storeArray(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
         
    //         foreach ($request->all as $list) {
    //             $data_array = [
    //                 'username' => $list['username'],
    //                 'email' => $list['username'] . '@gmail.com',
    //                 'password' => bcrypt($list['username']),
    //                 'fullname' => $list['username'],
    //                 'branch_id' => $list['branch_id'],
    //                 'position' => $list['position'],
    //                 'no_ktp' => '',
    //                 'alamat' =>'',
    //                 'gender' => '',
    //                 'mobile_number' =>'',
    //                 'status' => 'Active',
    //                 'created_by' => 'SYSTEM'
    //             ];
            
    //             User::create($data_array);
    //         } 
    
    //         DB::commit();
    //         ActivityLogger::logActivity($request,"Success",200);
    //         return response()->json(['message' => 'created successfully',"status" => 200], 200);
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,'Data Not Found',404);
    //         return response()->json(['message' => 'Hr Employee Id Not Found', "status" => 404], 404);
    //     }catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,$e->getMessage(),409);
    //         return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request,$e->getMessage(),500);
    //         return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
    //     }
    // }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'cabang_id' => 'required|string'
            ]);

            $getUsername = $request->username;

            $userByUsername = $this->usersRepository->findUserByUsername($getUsername);

            if ($userByUsername) {
                throw new Exception("Username Is Exist", 404);
            }

            $check_branch = M_Branch::find($request->cabang_id);

            if (!$check_branch) {
                throw new Exception("Id Branch Not Found", 404);
            }

            $this->usersRepository->create($request);

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'cabang_id' => 'required|string'
            ]);

            $userById = $this->usersRepository->findUserByid($id);

            if (!$userById) {
                throw new Exception("Users Not Found", 404);
            }

            $this->usersRepository->update($request, $userById);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

    public function destroy(Request $request,$id)
    { 
        DB::beginTransaction();
        try {
            $userById = $this->usersRepository->findUserByid($id);

            if (!$userById) {
                throw new Exception("Users Not Found", 404);
            }

            $this->usersRepository->delete($request, $userById);

            DB::commit();
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

    // public function uploadImage(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {

    //         $this->validate($request, [
    //             'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
    //             'type' => 'required|string'
    //         ]);

    //         $employee =  $request->employee_id;

    //         $folderPath = 'public/Employee_Image/' . $employee;

    //         if (!Storage::exists($folderPath)) {
    //             Storage::makeDirectory($folderPath);
    //         }

    //         $image_path = $request->file('image')->store($folderPath);
    //         $image_path = str_replace('public/', '', $image_path);

    //         $url = URL::to('/') . '/storage/' . $image_path;

    //         $data_array_attachment = [
    //             'ID' => Uuid::uuid7()->toString(),
    //             'USERS_ID' => '',
    //             'TYPE' => $request->type,
    //             'PATH' => $url ?? ''
    //         ];

    //         M_HrEmployeeDocument::create($data_array_attachment);

    //         DB::commit();
    //         ActivityLogger::logActivity($request, "Success", 200);
    //         return response()->json(['message' => 'Image upload successfully', "status" => 200], 200);
    //     } catch (QueryException $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request, $e->getMessage(), 409);
    //         return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         ActivityLogger::logActivity($request, $e->getMessage(), 500);
    //         return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
    //     }
    // }

    public function changePassword(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!Auth::guard('web')->attempt($request->only('username', 'password'))) {
                return response()->json(['message' => 'Invalid Credential'], 401);
            }
    
            // Perbarui kata sandi
            $request->user()->update(['password' => bcrypt($request->new_password)]);
            $request->user()->tokens()->delete();
    
            DB::commit();
            return response()->json(['message' => 'Kata sandi berhasil diperbarui'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }
    

    public function resetPassword(Request $request)
    {
        DB::beginTransaction();
        try {    
            $request->validate([
                'username' => 'required|string'
            ]);

            $userByUsername = $this->usersRepository->findUserByUsername($request->username);

            if (!$userByUsername) {
                throw new Exception("User Not Found", 404);
            }

            $this->usersRepository->resetPassword($request, $userByUsername);
    
            DB::commit();
            return response()->json(['message' => 'reset password successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            $this->log->logError($e, $request);
            return response()->json(['message' => "Internal Server Error"], 500);
        }
    }

}

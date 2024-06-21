<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_MasterMenu;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAccessMenuController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data =  M_MasterUserAccessMenu::all();

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function show(Request $req,$id)
    {
        try {
            User::where('id',$id)->whereNull('deleted_at')->firstOrFail();

            $user_menu_list = M_MasterUserAccessMenu::with('masterMenu:id,menu_name,leading')
                                                    ->where('users_id',$id)->get();

            $data=[];
            foreach ($user_menu_list as $menu) {
                if ($menu['masterMenu'] !== null) {
                    $data[] = [
                        "id" => $menu['masterMenu']['id'],
                        "menu_name" => $menu['masterMenu']['menu_name'],
                        "leading" => $menu['masterMenu']['leading']
                    ];
                }
            }

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'OK',"status" => 200,'response' => $data], 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Users Id Data Not Found',404);
            return response()->json(['message' => 'Data Not Found',"status" => 404], 404);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $request->validate([
                'users_id' => 'required|string',
            ]);

            if (collect($request->menu_list)->isEmpty()) {
                throw new Exception("Menu List Tidak Boleh Kosong",404);
            }elseif(!is_array($request->menu_list)){
                throw new Exception("Menu List Harus tipe Array",404);
            }

            $users_check = User::where('id',$request->users_id)->whereNull('deleted_at')->first();

            if (!$users_check) {
                throw new Exception("Users Id Not Found",404);
            }

            foreach ($request->menu_list as $value) {

                $menu_check = M_MasterMenu::where('id',$value)->whereNull('deleted_at')->first();

                if (!$menu_check) {
                    throw new Exception("Menu Id Not Found",404);
                }

                $data_insert = [
                    'master_menu_id' => $value['menu_id'],
                    'users_id'=> $request->users_id,
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                 M_MasterUserAccessMenu::create($data_insert);
            }
            
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User Access menu created successfully',"status" => 200], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(),"status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function update(Request $request,$id)
    {
        DB::beginTransaction();
        try {
            $checks = M_MasterUserAccessMenu::where('users_id', $id)->get();

            if($checks->isEmpty()){
                throw new Exception("Users Id Not Found",404);
            }

            foreach ($checks as $check) {
                $check->update([
                    'deleted_by' => $request->user()->id,
                    'deleted_at' => Carbon::now()
                ]);
            }

            foreach ($request->menu_list as $value) {

                $menu_check = M_MasterMenu::where('id',$value)->first();

                if (!$menu_check) {
                    throw new Exception("Menu Id Not Found",404);
                }

                $data_insert = [
                    'master_menu_id' => $value['menu_id'],
                    'users_id'=> $id,
                    'created_by' => $request->user()->id,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                 M_MasterUserAccessMenu::create($data_insert);
            }

            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'User Access Menu updated successfully', "status" => 200], 200);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function destroy(Request $req,$id)
    { 
        DB::beginTransaction();
        try {
            
            $userAccessMenu = M_MasterUserAccessMenu::findOrFail($id);

            $update = [
                'deleted_by' => $req->user()->id,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $userAccessMenu->update($update);

            DB::commit();
            ActivityLogger::logActivity($req,"Success",200);
            return response()->json(['message' => 'User Access Menu deleted successfully', "status" => 200], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,'User Access Menu Id Data Not Found',404);
            return response()->json(['message' => 'User Access Menu Id Data Not Found', "status" => 404], 404);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

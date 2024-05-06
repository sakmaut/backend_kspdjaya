<?php

namespace App\Http\Controllers\API\Menu;

use App\Http\Controllers\API\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_Role;
use App\Models\M_Role;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterRoleController extends Controller
{
    public function index()
    {
        $data = M_Role::all();
        $dto = R_Role::collection($data);
        
        return response()->json(['message' => 'OK', "status" => 200, 'response' => $dto], 200);
    }

    public function _validate($request)
    {
        $validator = $request->validate([
            'role_name' => 'required|string',
            'status' => 'required|string',
            // 'master_menu_id' => 'required|string',
            // 'view' => 'required|string',
            // 'insert' => 'required|string',
            // 'update' => 'required|string',
            // 'delete' => 'required|string',
            // 'export' => 'required|string',
            // 'import' => 'required|string'
        ]);

        return $validator;
    }

    public function store(Request $req)
    {
        try {
            DB::beginTransaction();
            $validator = $this->_validate($req);

            // foreach ($req->penjamin as $result) {

            //     $data_array_person = [
            //         'id' => Uuid::uuid4()->toString(),
            //         'cr_prospect_id' => $crProspek->id,
            //         'nama' => $result['nama'] ?? '',
            //         'ktp' => $result['ktp'] ?? '',
            //         'tgl_lahir' => $result['tgl_lahir'] ?? null,
            //         'pekerjaan' => $result['pekerjaan'] ?? '',
            //         'status' => $result['status'] ?? ''
            //     ];
            
            //     M_CrProspectPerson::create($data_array_person);
            // }

            $validator['created_by'] = $req->user()->id;

            M_Role::create($validator);

            DB::commit();
            return response()->json(['message' => 'Master Role created successfully', "status" => 200], 200);
        } catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage(), "status" => 409], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($req,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }
}

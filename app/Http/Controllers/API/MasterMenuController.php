<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\ActivityLogger;
use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Repositories\Menu\MenuRepository;
use App\Http\Resources\R_MasterMenu;
use Illuminate\Http\Request;
use App\Models\M_MasterMenu;
use App\Models\M_MasterUserAccessMenu;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MasterMenuController extends Controller
{

    protected $menuRepository;
    protected $log;

    public function __construct(MenuRepository $menuRepository, ExceptionHandling $log)
    {
        $this->menuRepository = $menuRepository;
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getListActiveMenu = $this->menuRepository->getListActiveMenu();

            $dto = R_MasterMenu::collection($getListActiveMenu);

            return response()->json(['message' => 'OK', 'response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $findActiveMenu = $this->menuRepository->findActiveMenu($id);

            $dto = new R_MasterMenu($findActiveMenu);

            return response()->json(['message' => 'OK', 'response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'menu_name' => 'required|string',
                'route' => 'required|string',
                'order' => 'numeric',
                'leading' => 'string'
            ]);

            $this->menuRepository->create($request);

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $request->validate([
                'menu_name' => 'unique:master_menu,menu_name,' . $id,
            ]);

            $this->menuRepository->update($request, $id);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $this->menuRepository->delete($request, $id);

            DB::commit();
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function menuSubList(Request $request)
    {
        try {
            $data =  $this->menuRepository->getListAccessMenuUser($request);

            // $data = M_MasterMenu::buildMenuArray($request);

            return response()->json(['message' => 'OK', 'response' => $data], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}

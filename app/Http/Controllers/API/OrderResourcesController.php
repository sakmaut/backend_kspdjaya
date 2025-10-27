<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\M_OrderResources;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderResourcesController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $resources = M_OrderResources::whereNull('DELETED_BY')
                ->orWhere('DELETED_BY', '')
                ->orderBy('CREATED_AT', 'desc')
                ->get()
                ->map(function ($item) {
                    $item->STATUS = $item->STATUS ?: 'Aktif';
                    return $item;
                });;

            return response()->json($resources, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $resource = M_OrderResources::where('ID', $id)
                ->where(function ($query) {
                    $query->whereNull('DELETED_BY')
                        ->orWhere('DELETED_BY', '');
                })
                ->first();

            if (!$resource) {
                throw new \Exception("Resource not found", 404);
            }

            return response()->json($resource, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            $fields = [
                'KODE' => $this->createAutoCode(M_OrderResources::class, 'KODE', 'REF'),
                'NAMA' => $request->nama ?? "",
                'NO_HP' => $request->no_hp ?? "",
                'KETERANGAN' => $request->keterangan ?? "",
                'STATUS' => $request->status ?? "Aktif",
                'CREATED_BY'  => $request->user()->id ?? null,
                'CREATED_AT' => Carbon::now('Asia/Jakarta'),
            ];

            M_OrderResources::create($fields);

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
            // Validasi data
            $request->validate([
                'nama' => 'required|string|max:255',
                'no_hp' => 'nullable|string|max:20',
                'keterangan' => 'nullable|string'
            ]);

            // Cari data berdasarkan ID
            $resource = M_OrderResources::findOrFail($id);

            // Update field
            $resource->update([
                'NAMA' => $request->nama ?? $resource->nama,
                'NO_HP' => $request->no_hp ?? $resource->NO_HP,
                'KETERANGAN' => $request->keterangan ?? $resource->KETERANGAN,
                'STATUS' => $request->status ?? $resource->STATUS,
                'UPDATED_BY' => $request->user()->id ?? null,
                'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
            ]);

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
            $resource = M_OrderResources::findOrFail($id);

            $resource->update([
                'DELETED_BY' => $request->user()->id ?? null,
                'DELETED_AT' => Carbon::now('Asia/Jakarta'),
            ]);

            DB::commit();
            return response()->json(['message' => 'deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }

    public function statusUpdate(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = $request->id ?? $request->ID;

            $resource = M_OrderResources::findOrFail($id);

            $resource->update([
                'STATUS' => $request->status ?? $resource->STATUS ?? "Aktif",
                'UPDATED_BY' => $request->user()->id ?? null,
                'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
            ]);

            DB::commit();
            return response()->json(['message' => 'updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

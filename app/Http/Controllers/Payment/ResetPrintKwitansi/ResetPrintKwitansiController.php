<?php

namespace App\Http\Controllers\Payment\ResetPrintKwitansi;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\ResetPrintKwitansi\ResetPrintKwitansiLog\ResetPrintKwitansiLogModel;
use App\Models\M_LogPrint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetPrintKwitansiController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $results = M_LogPrint::all();

            $dto = ResetPrintKwitansiResources::collection($results);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'NoKwitansi' => 'required|string'
            ]);

            $user = $request->user()->username ?? 'SYSTEM';

            $logPrint = M_LogPrint::where('ID', $request->NoKwitansi)->first();

            if (!$logPrint) {
                return response()->json([
                    'message' => 'Data kwitansi tidak ditemukan'
                ], 404);
            }

            $logPrint->update([
                'COUNT'     => 0,
                'RESETTER_BY'  => $user,
                'RESETTER_AT'  => now()
            ]);

            ResetPrintKwitansiLogModel::create([
                'id'           => null,
                'log_print_id' => $logPrint->ID,
                'description'  => $request->Keterangan ?? "",
                'created_by'   => $user,
                'created_at'   => now()
            ]);

            DB::commit();

            return response()->json([
                'message'      => 'Counter berhasil di-reset',
                'NoKwitansi'   => $logPrint->ID,
                'JumlahPrint'  => 0
            ], 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return $this->log->logError($e, $request);
        }
    }
}

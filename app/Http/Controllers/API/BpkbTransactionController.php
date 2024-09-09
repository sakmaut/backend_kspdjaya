<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_BpkbDetail;
use App\Models\M_BpkbTransaction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BpkbTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {

            $branch = $request->user()->branch_id;

            $data = M_BpkbTransaction::where('FROM_BRANCH',$branch)->get();

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($data, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = [
                'FROM_BRANCH' => $request->user()->branch_id,
                'TO_BRANCH' => $request->tujuan,
                'CATEGORY' => $request->kategori??null,
                'NOTE' => $request->catatan,
                'CREATE_BY' => Carbon::now()->format('Y-m-d'),
                'CREATE_AT' => $request->user()->id
            ];

           $transaction = M_BpkbTransaction::create($data);

           if(!empty($request->bpkb) && is_array($request->bpkb)){

                foreach ($request->bpkb as $res) {
                    $detail = [
                        'BPKB_TRANSACTION_ID' => $transaction->ID,
                        'COLLATERAL_ID' => $res['id'],
                    ];

                    M_BpkbDetail::create($detail);
                } 
           }
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'created successfully'], 200);
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

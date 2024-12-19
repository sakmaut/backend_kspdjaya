<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_BpkbList;
use App\Models\M_BpkbApproval;
use App\Models\M_BpkbDetail;
use App\Models\M_BpkbTransaction;
use App\Models\M_CrCollateral;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid as Uuid;

class BpkbTransactionController extends Controller
{


    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $position = $user->position??null;
            $branch = $user->branch_id??null;
            
            if($position == "HO"){
                $data = M_BpkbTransaction::where('TO_BRANCH','ho')->get();
            }else{
                $data = M_BpkbTransaction::where('FROM_BRANCH',$branch)->get();
            }

            $dto = R_BpkbList::collection($data);

            ActivityLogger::logActivity($request,"Success",200);
            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            if (!isset($request->bpkb) || empty($request->bpkb)) {
                throw new Exception("bpkb not found!!!");
            }

            $user = $request->user();
            $position = $user->position??null;
            $branch = $user->branch_id??null;

            if($position == 'HO'){
                $id_bpkb_transaction = $request->id_surat;

                $check = M_BpkbTransaction::where('ID',$id_bpkb_transaction)->first();

                if(!$check){
                    throw new Exception('Id Surat Not Found');
                }

                $check->update([
                    'CATEGORY' => $request->type??null,
                    'NOTE' => $request->catatan,
                    'STATUS' => 'APPROVE_HO'
                ]);

                $data_approval = [
                    'BPKB_TRANSACTION_ID' => $id_bpkb_transaction,
                    'ONCHARGE_APPRVL' => $request->flag_approval??null,
                    'ONCHARGE_PERSON' => $user->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_DESCR' => $request->catatan,
                    'APPROVAL_RESULT' => 'APPROVE_HO'
                ];
    
                M_BpkbApproval::create($data_approval);

                if(!empty($request->bpkb) && is_array($request->bpkb)){

                    $requestCollateralIds = collect($request->bpkb)->pluck('id')->toArray();

                    $getList = M_BpkbDetail::where('BPKB_TRANSACTION_ID', $id_bpkb_transaction)->get();

                    // Create an associative array to map existing COLLATERAL_IDs to their records
                    $existingRecords = $getList->keyBy('COLLATERAL_ID');

                    $collateralIdsToUpdateNo = [];

                    foreach ($getList as $record) {
                        $collateralId = $record->COLLATERAL_ID;
                    
                        if (in_array($collateralId, $requestCollateralIds)) {
                            // Update status to 'yes' if the collateral ID exists in the request
                            $record->update([
                                'STATUS' => 'yes',
                                'UPDATED_BY' => $user->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);
                            
                            // Remove from the list of collateral IDs to be updated to 'no'
                            $requestCollateralIds = array_diff($requestCollateralIds, [$collateralId]);
                        } else {
                            // Collect IDs that need to be updated to 'no'
                            $collateralIdsToUpdateNo[] = $collateralId;
                        }
                    }

                    if (!empty($collateralIdsToUpdateNo)) {
                        M_BpkbDetail::where('BPKB_TRANSACTION_ID', $id_bpkb_transaction)
                            ->whereIn('COLLATERAL_ID', $collateralIdsToUpdateNo)
                            ->update([
                                'STATUS' => 'no',
                                'UPDATED_BY' => $user->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);
                    }

                    // if (!empty($collateralIdsToUpdateNo)) {
                    //     $collaterals = M_CrCollateral::whereIn('ID', $collateralIdsToUpdateNo)->get();
                    //     foreach ($collaterals as $collateral) {
                    //         $collateral->update(['LOCATION_BRANCH' => $check->FROM_BRANCH]);
                    //     }
                    // }
                }
            }else{
                $data = [
                    'TRX_CODE' => generateCodePrefix($request, 'bpkb_transaction', 'TRX_CODE','JMN'),
                    'FROM_BRANCH' => $branch,
                    'TO_BRANCH' => $request->tujuan,
                    'CATEGORY' => $request->type??null,
                    'NOTE' => $request->catatan,
                    'STATUS' => 'SENDING',
                    'COURIER' => $request->kurir??null,
                    'CREATED_BY' => $user->id
                ];
    
                $transaction = M_BpkbTransaction::create($data);
                $id = $transaction->ID;
    
                $data_approval = [
                    'BPKB_TRANSACTION_ID' => $id,
                    'ONCHARGE_APPRVL' => 'sending',
                    'ONCHARGE_PERSON' => $user->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_DESCR' => $request->catatan,
                    'APPROVAL_RESULT' => 'SENDING'
                ];
    
                M_BpkbApproval::create($data_approval);
    
                if(!empty($request->bpkb) && is_array($request->bpkb)){
    
                    $details = [];
                    $collateralIds = [];
            
                    foreach ($request->bpkb as $res) {
                        $details[] = [
                            'ID' => Uuid::uuid7()->toString(),
                            'BPKB_TRANSACTION_ID' => $transaction->ID,
                            'COLLATERAL_ID' => $res['id'],
                            'STATUS' => 'SENDING'
                        ];
                        $collateralIds[] = $res['id'];
                    }
    
                    M_BpkbDetail::insert($details);
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

    public function approval(Request $request)
    {
        try {
            $request->validate([
                'no_surat' => 'required|string',
                'flag' => 'required|string',
            ]);
     
            $check = M_BpkbTransaction::where('TRX_CODE', $request->no_surat)->first();

            if (!$check) {
                throw new Exception("no surat is not found.", 404);
            }

            $check->update([
                'STATUS' => 'NORMAL',
            ]);

            if(!empty($request->bpkb) && is_array($request->bpkb)){
    
                foreach ($request->bpkb as $res) {

                    $bpkbDetail  = M_BpkbDetail::where('ID', $res['id'])->first();

                    if (!$bpkbDetail) {
                        throw new Exception("bpkb detail is not found.", 404);
                    }

                    $bpkbDetail->update([
                        'STATUS' => 'NORMAL'
                    ]);
                
                    M_CrCollateral::where('ID', $bpkbDetail->COLLATERAL_ID)
                        ->update([
                            'LOCATION_BRANCH' => 'HO'
                        ]);
                }
            }

            $flag = $request->flag;
    
            $approvalDataMap = [
                'yes' => ['code' => 'APHO', 'result' => 'disetujui ho'],
                'revisi' => ['code' => 'REORHO', 'result' => 'ada revisi ho'],
                'no' => ['code' => 'CLHO', 'result' => 'dibatalkan ho'],
            ];
    
            $approvalData = $approvalDataMap[$flag] ?? $approvalDataMap['no'];

            $data_log = [
                'ID' => Uuid::uuid7()->toString(),
                'BPKB_TRANSACTION_ID' => $check->ID,
                'ONCHARGE_APPRVL' => $approvalData['code'],
                'ONCHARGE_PERSON' => $request->user()->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'ONCHARGE_DESCR' => '',
                'APPROVAL_RESULT' => $approvalData['result']
            ];
             
            M_BpkbApproval::create($data_log);
    
            // Return success response
            return response()->json(['message' => 'Approval Successfully'], 200);
    
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), 'status' => 500], 500);
        }
    }
}

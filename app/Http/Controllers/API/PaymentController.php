<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\R_Branch;
use App\Http\Resources\R_BranchDetail;
use App\Models\M_Branch;
use App\Models\M_CreditSchedule;
use App\Models\M_Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {   

            M_CreditSchedule::where([
                'loan_number' => $request->loan_number,
                'PAYMENT_DATE' => $request->tgl_bayar
            ])->update([
                'PAID_FLAG' => 'paid',
            ]);
            
            $getCodeBranch = M_Branch::find($request->user()->branch_id);

            $arrayData = [  
                'ID' => Uuid::uuid7()->toString(),
                'STTS_RCRD' => 'A',
                'INVOICE' => generateCode($request, 'payment', 'INVOICE','INV'),
                'BRANCH' => $getCodeBranch->CODE_NUMBER,
                'LOAN_NUM' => $request->loan_number,
                'VALUE_DATE' => $request->tgl_bayar,
                
                'ENTRY_DATE' => Carbon::now(),
                'TITLE' => $request->ket_pembayaran,
                'ORIGINAL_AMOUNT' => $request->nilai_pembayaran,
                'OS_AMOUNT' => 0,
                'AUTH_BY' => $request->user()->id,
                'AUTH_DATE' => Carbon::now(),
                'BANK_NAME' => $request->nama_bank??null,
                'BANK_ACC_NUMBER' => $request->no_rekening??null,
            ];

            M_Payment::create($arrayData);
    
            DB::commit();
            ActivityLogger::logActivity($request,"Success",200);
            return response()->json(['message' => 'created successfully'], 200);
        }catch (QueryException $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),409);
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            DB::rollback();
            ActivityLogger::logActivity($request,$e->getMessage(),500);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    function generateInvoice($id)
    {
        $lastCode = DB::table('branch')->where('ID',$id)->first();

        if ($lastCode) {
            $lastCodeNumber = (int) substr($lastCode->CODE_NUMBER, 1);
            $newCodeNumber = $lastCodeNumber + 1;
            $newCode = str_pad($newCodeNumber, 3, '0', STR_PAD_LEFT);
        } else {
            $newCode = '001';
        }

        return $newCode;
    }
}

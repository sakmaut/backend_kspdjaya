<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_LogPrint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogPrintController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->validate($request, [
                'id' => 'required|string',
            ]);
            
            $counter = M_LogPrint::where('ID', $request->id)->first();

            if (!$counter) {
                $data = [
                    'ID' => $request->id ?? '',
                    'COUNT' => 1, 
                    'PRINT_BRANCH' => $request->user()->branch_id ?? '',
                    'PRINT_POSITION' => $request->user()->position,
                    'PRINT_BY' => $request->user()->fullname,
                    'PRINT_DATE' => now(),
                ];
            
                M_LogPrint::create($data);
            } else {
                $counter->update([
                    'COUNT' => $counter->COUNT + 1,
                    'PRINT_BRANCH' => $request->user()->branch_id ?? '',
                    'PRINT_POSITION' => $request->user()->position,
                    'PRINT_BY' => $request->user()->fullname,
                    'PRINT_DATE' => now(),
                ]);
            }
            
    
            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        }catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }
}

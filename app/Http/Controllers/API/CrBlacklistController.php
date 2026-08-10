<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_CrBlacklist;
use App\Models\M_Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CrBlacklistController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = M_CrBlacklist::all()->map(function ($item) {
                $item->STATUS = $item->STATUS ?? 'ACTIVE';
                return $item;
            });

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        }
    }

    public function check(Request $req)
    {
        try {
            $param = $req->param;
            
            $customer = M_Customer::where('ID_NUMBER', $param)
                ->orderBy('CREATE_DATE', 'desc')
                ->first();

            if ($customer) {
                $roResult = DB::select('CALL sp_get_max_od_by_customer(?)', [$customer->CUST_CODE]);

                $row   = $roResult[0] ?? null;
                $maxOd = $row->OD2 ?? null;

                if ($row !== null && $maxOd !== null && $maxOd > 90) {
                    $note = "OD lebih dari 90 hari (OD: {$maxOd})";

                    $existing = M_CrBlacklist::where('KTP', $row->ID_NUMBER)->first();

                    if ($existing) {
                        $existing->update(['NOTE' => $note]);
                    } else {
                        M_CrBlacklist::create([
                            'ID'          => Uuid::uuid7()->toString(),
                            'LOAN_NUMBER' => $row->LOAN_NUMBER ?? null,
                            'NAME'        => $row->NAME ?? null,
                            'KTP'         => $row->ID_NUMBER ?? null,
                            'KK'          => $row->KK_NUMBER ?? null,
                            'NOTE'        => $note,
                            'STATUS'      => 'ACTIVE',
                            'PERSON'      => 'SYSTEM',
                            'DATE_ADD'    => Carbon::now('Asia/Jakarta'),
                        ]);
                    }
                }
            }


            $blacklist = M_CrBlacklist::where(function ($q) use ($param) {
                $q->where('LOAN_NUMBER', $param)
                    ->orWhere('KTP', $param)
                    ->orWhere('KK', $param);
            })
                ->where(function ($q) {
                    $q->where('STATUS', 'ACTIVE')
                        ->orWhere('STATUS', '')
                        ->orWhereNull('STATUS');
                })
                ->selectRaw("
                        LOAN_NUMBER,
                        KTP,
                        KK,
                        (
                            SELECT GROUP_CONCAT(NOTE)
                            FROM cr_blacklist
                            WHERE (LOAN_NUMBER = ? OR KTP = ? OR KK = ?)
                            AND STATUS = 'ACTIVE'
                        ) as notes
                ", [$param, $param, $param])
                ->first();

            ActivityLogger::logActivity($req,"Success",200);
            return response()->json($blacklist, 200);
        } catch (ModelNotFoundException $e) {
            ActivityLogger::logActivity($req,'Data Not Found',404);
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

            $arrayData = [  
                'LOAN_NUMBER' => $request->loan_number??null,
                'KTP' => $request->ktp??null,
                'KK' => $request->kk??null,
                'NOTE' => $request->note??null
            ];

            M_CrBlacklist::create($arrayData);

            DB::commit();
            return response()->json(['message' => 'Data Created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = M_CrBlacklist::findOrFail($id);

            $oldNote = $data->NOTE ?? '';
            $newNote = $request->note ?? '';

            $combinedNote = $oldNote;

            if (!empty($newNote)) {
                $combinedNote = trim($oldNote . "\n" . now()->format('Y-m-d H:i:s') . ' - ' . $newNote);
            }

            $data->update([
                'NOTE'       => $combinedNote,
                'STATUS'     => 'INACTIVE',
                'UPDATED_BY' => $request->user()->id ?? '',
                'UPDATED_AT' => now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

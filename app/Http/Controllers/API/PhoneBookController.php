<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_PhoneBook;
use App\Models\M_Customer;
use App\Models\M_CustomerPhone;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhoneBookController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function show(Request $request, $id)
    {
        try {
            $cus = M_Customer::with('phone_book')->find($id);

            if (!$cus) {
                throw new Exception("Customer Id Not Found", 404);
            }

            $dto = new R_PhoneBook($cus);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = [
                'CUSTOMER_ID' => $request->customer_id ?? '',
                'ALIAS' => $request->alias ?? '',
                'PHONE_NUMBER' => $request->no_hp ?? '',
                'CREATED_AT' => Carbon::now()->format('Y-m-d') ?? null,
                'CREATED_BY' => $request->user()->id ?? '',
            ];

            M_CustomerPhone::create($data);

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->log->logError($e, $request);
        }
    }
}

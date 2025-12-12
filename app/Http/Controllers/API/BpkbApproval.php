<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_BpkbApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BpkbApproval extends Controller
{

    protected $request;
    protected $entityBpkbApproval;

    public function __construct(Request $request, M_BpkbApproval $entityBpkbApproval)
    {
        $this->request = $request;
        $this->entityBpkbApproval = $entityBpkbApproval;
    }

    public function create(string $trxId, string $descr, string $status): void
    {
        $log = $this->prepareLogData($trxId, $descr, $status);
        $this->entityLocationStatus->create($log);
    }

    protected function prepareLogData(string $trxId, string $descr, string $status): array
    {
        return [
            'BPKB_TRANSACTION_ID' => $trxId,
            'ONCHARGE_APPRVL' => $status,
            'ONCHARGE_PERSON' => $this->request->user()->id,
            'ONCHARGE_TIME' => Carbon::now('Asia/Jakarta'),
            'ONCHARGE_DESCR' => $descr,
            'APPROVAL_RESULT' => $status
        ];
    }
}

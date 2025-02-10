<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_LocationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LocationStatus extends Controller
{
    protected $request;
    protected $entityLocationStatus;

    public function __construct(Request $request, M_LocationStatus $entityLocationStatus)
    {
        $this->request = $request;
        $this->entityLocationStatus = $entityLocationStatus;
    }

    public function createLocationStatusLog(string $trxId, string $descr, string $status): void
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
            'ONCHARGE_TIME' => Carbon::now(),
            'ONCHARGE_DESCR' => $descr,
            'APPROVAL_RESULT' => $status
        ];
    }
}

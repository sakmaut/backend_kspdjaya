<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_BpkbApproval;
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

    public function create(string $collateralId, string $location, string $status = 'NEW'): void
    {
        $log = $this->prepareLogData($collateralId, $location, $status);
        $this->entityLocationStatus->create($log);
    }

    protected function prepareLogData(string $collateralId, string $location, string $status): array
    {
        return [
            'TYPE' => 'kendaraan',
            'COLLATERAL_ID' => $collateralId,
            'LOCATION' => $location,
            'STATUS' => $status,
            'CREATE_BY' => $this->request->user()->id,
            'CREATED_AT' => now(),
        ];
    }
}

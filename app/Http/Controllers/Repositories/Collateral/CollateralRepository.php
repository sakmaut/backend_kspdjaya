<?php

namespace App\Http\Controllers\Repositories\Collateral;

use App\Http\Controllers\API\LocationStatus;
use App\Http\Controllers\Controller;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralRequest;
use App\Models\M_Credit;
use App\Models\M_Payment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CollateralRepository implements CollateralInterface
{
    protected $collateralEntity;
    protected $locationStatus;
    protected $collateralRequestEntity;

    function __construct(M_CrCollateral $collateralEntity, M_CrCollateralRequest $collateralRequestEntity, LocationStatus $locationStatus)
    {
        $this->collateralEntity = $collateralEntity;
        $this->locationStatus = $locationStatus;
        $this->collateralRequestEntity = $collateralRequestEntity;
    }

    function findCollateralById($id)
    {
        return $this->collateralEntity::where('ID', $id)->first();
    }

    function getListAllCollateral()
    {
        $query = $this->collateralEntity::with(['credit', 'originBranch', 'currentBranch']);

        return $query;
    }

    function queryCollateralList()
    { {
            $results = DB::table('cr_collateral as a')
                ->select(
                    'a.ID',
                    'a.BRAND',
                    'a.TYPE',
                    'a.PRODUCTION_YEAR',
                    'a.COLOR',
                    'a.ON_BEHALF',
                    'a.POLICE_NUMBER',
                    'a.ENGINE_NUMBER',
                    'a.CHASIS_NUMBER',
                    'a.BPKB_ADDRESS',
                    'a.BPKB_NUMBER',
                    'a.STNK_NUMBER',
                    'a.STNK_VALID_DATE',
                    'a.INVOICE_NUMBER',
                    'a.VALUE',
                    'b.LOAN_NUMBER',
                    'b.CREATED_AT',
                    'c.NAME as originBranch',
                    'd.NAME as currentBranch'
                )
                ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
                ->leftJoin('branch as c', 'c.ID', '=', 'a.COLLATERAL_FLAG')
                ->leftJoin('branch as d', 'd.ID', '=', 'a.LOCATION_BRANCH');

            return $results;
        }
    }

    function searchCollateralList($request)
    {
        $no_kontrak = $request->query('no_kontrak');
        $atas_nama = $request->query('atas_nama');
        $no_polisi = $request->query('no_polisi');
        $no_bpkb = $request->query('no_bpkb');

        $query = $this->getListAllCollateral();

        if (!empty($no_kontrak)) {
            $query->whereHas('credit', function ($query) use ($no_kontrak) {
                $query->where('LOAN_NUMBER', $no_kontrak);
            });
        }

        if (!empty($atas_nama)) {
            $query->where('ON_BEHALF', 'like', '%' . $atas_nama . '%');
        }

        if (!empty($no_polisi)) {
            $query->where('POLICE_NUMBER', 'like', '%' . $no_polisi . '%');
        }

        if (!empty($no_bpkb)) {
            $query->where('BPKB_NUMBER', 'like', '%' . $no_bpkb . '%');
        }

        $query->whereHas('credit', function ($query) {
            $query->orderBy('CREATED_AT', 'DESC');
        });

        $query = $query->limit(10)->get();

        return $query;
    }

    function update($request, $collateralId)
    {
        $findCollateralById = $this->findCollateralById($collateralId);

        if (!$findCollateralById) {
            throw new Exception('Collateral Id Not Found', 404);
        }

        $data = [
            'COLLATERAL_ID' => $collateralId,
            'ON_BEHALF' => $request->atas_nama ?? '',
            'POLICE_NUMBER' => $request->no_polisi ?? '',
            'ENGINE_NUMBER' => $request->no_mesin ?? '',
            'CHASIS_NUMBER' => $request->no_rangka ?? '',
            'BPKB_ADDRESS' => $request->alamat_bpkb ?? '',
            'BPKB_NUMBER' => $request->no_bpkb ?? '',
            'STNK_NUMBER' => $request->no_stnk ?? '',
            'INVOICE_NUMBER' => $request->no_faktur ?? '',
            'STNK_VALID_DATE' => $request->tgl_stnk ?? null,
            'REQUEST_BY' => $request->user()->id ?? '',
            'REQUEST_BRANCH' => $request->user()->branch_id ?? '',
            'REQUEST_POSITION' => $request->user()->position ?? '',
            'REQUEST_AT' => Carbon::now() ?? '',
        ];

        return $this->collateralRequestEntity::create($data);
    }

    function collateral_status($request)
    {
        $colId = $request->collateral_id;
        $findCollateralById = $this->findCollateralById($colId);

        if (!$findCollateralById) {
            throw new Exception('Collateral Id Not Found', 404);
        }

        $credit = M_Credit::find($findCollateralById->CR_CREDIT_ID);
        $status = 'NORMAL';

        switch (strtolower($request->status)) {
            case 'titip':
                $status = 'TITIP';
                break;
            case 'sita':
                $status = 'SITA';
                break;
            case 'jual':
                $status = 'JUAL';
                break;
        }

        $now = Carbon::now();
        $userId = $request->user()->id ?? '';

        switch ($status) {
            case 'TITIP':
                if ($credit) {
                    $credit->update([
                        'STATUS_REC' => 'PU',
                        'MOD_DATE' => $now,
                        'MOD_USER' => $userId,
                    ]);
                }

                $this->locationStatus->createLocationStatusLog($colId, $request->user()->branch_id, 'TITP');
                break;
            case 'SITA':
                if ($credit) {
                    $credit->update([
                        'STATUS_REC' => 'RP',
                        'STATUS' => 'D',
                        'MOD_DATE' => $now,
                        'MOD_USER' => $userId,
                    ]);
                }

                $this->locationStatus->createLocationStatusLog($colId, $request->user()->branch_id, 'SITA');
                break;

            case 'JUAL':
                if ($credit->STATUS_REC == 'RP') {
                    M_Payment::create([
                        'ID' => Uuid::uuid7()->toString(),
                        'ACC_KEY' => 'JUAL UNIT',
                        'STTS_RCRD' => 'PAID',
                        'PAYMENT_METHOD' => 'cash',
                        'BRANCH' => $request->user()->branch_id ?? '',
                        'LOAN_NUM' => $credit->LOAN_NUMBER ?? '',
                        'ENTRY_DATE' => now(),
                        'TITLE' => 'JUAL UNIT TARIKAN',
                        'ORIGINAL_AMOUNT' => $request->harga ?? 0,
                        'USER_ID' => $userId,
                    ]);

                    $this->locationStatus->createLocationStatusLog($colId, $request->user()->branch_id, 'JUAL UNIT');
                } else {
                    throw new Exception("Jual Unit Not Available", 500);
                }
                break;

            case 'NORMAL':
                if ($credit && $credit->STATUS_REC != 'RP') {
                    $credit->update([
                        'STATUS_REC' => 'AC',
                        'MOD_DATE' => $now,
                        'MOD_USER' => $userId,
                    ]);

                    $this->locationStatus->createLocationStatusLog($colId, $request->user()->branch_id, 'NORMAL');
                } else {
                    throw new Exception("Credit Status Not Available", 500);
                }
                break;
        }

        $data = [
            'STATUS' => $status,
            'MOD_DATE' => $now,
            'MOD_BY' => $userId,
        ];

        return $findCollateralById->update($data);
    }
}

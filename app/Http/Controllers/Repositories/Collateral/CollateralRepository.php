<?php

namespace App\Http\Controllers\Repositories\Collateral;

use App\Http\Controllers\Controller;
use App\Models\M_CrCollateral;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class CollateralRepository implements CollateralInterface
{
    protected $collateralEntity;

    function __construct(M_CrCollateral $collateralEntity)
    {
        $this->collateralEntity = $collateralEntity;
    }

    function findCollateralById($id)
    {
        return $this->collateralEntity::where('ID', $id)->first();
    }

    function getListAllCollateral($branchId = null)
    {
        $query = $this->collateralEntity::with(['credit', 'originBranch', 'currentBranch']);

        if ($branchId) {
            $query->whereHas('credit', function ($query) use ($branchId) {
                $query->where('BRANCH', $branchId);
            });
        }

        return $query;
    }

    function searchCollateralList($request)
    {
        $no_kontrak = $request->query('no_kontrak');
        $atas_nama = $request->query('atas_nama');
        $no_polisi = $request->query('no_polisi');
        $no_bpkb = $request->query('no_bpkb');

        $getPosition = $request->user()->position;
        $getBranch = $request->user()->branch_id;

        $query = $this->getListAllCollateral();

        // if (in_array($getPosition, ['ho', 'superadmin'])) {
        //     $query = $this->getListAllCollateral();
        // } else {

        //     $query = $this->getListAllCollateral($getBranch);
        // }

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

        $query = $query->get();

        return $query;
    }

    function update($request, $colId)
    {
        $findCollateralById = $this->findCollateralById($colId);

        if (!$findCollateralById) {
            throw new Exception('Collateral Id Not Found', 404);
        }

        $data = [
            'BRAND' => $request->merk ?? '',
            'TYPE' => $request->tipe ?? '',
            'PRODUCTION_YEAR' => $request->tahun ?? '',
            'COLOR' => $request->warna ?? '',
            'ON_BEHALF' => $request->atas_nama ?? '',
            'POLICE_NUMBER' => $request->no_polisi ?? '',
            'ENGINE_NUMBER' => $request->no_mesin ?? '',
            'CHASIS_NUMBER' => $request->no_rangka ?? '',
            'BPKB_ADDRESS' => $request->alamat_bpkb ?? '',
            'BPKB_NUMBER' => $request->no_bpkb ?? '',
            'STNK_NUMBER' => $request->no_stnk ?? '',
            'INVOICE_NUMBER' => $request->no_faktur ?? '',
            'STNK_VALID_DATE' => $request->tgl_stnk ?? null,
            'MOD_DATE' => Carbon::now() ?? '',
            'MOD_BY' => $request->user()->id ?? '',
        ];

        return $findCollateralById->update($data);
    }
}

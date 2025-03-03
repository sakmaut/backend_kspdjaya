<?php

namespace App\Http\Controllers\Repositories\Branch;

use App\Http\Controllers\Component\GeneratedCode;
use App\Models\M_Branch;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class BranchRepository implements BranchRepositoryInterface
{
    protected $branchEntity;

    function __construct(M_Branch $branchEntity)
    {
        $this->branchEntity = $branchEntity;
    }

    function getActiveBranch()
    {
        return $this->branchEntity::where(function ($query) {
            $query->whereNull('DELETED_BY')
                ->orWhere('DELETED_BY', '');
        })
            ->get();
    }

    function findBranchById($id)
    {
        return $this->branchEntity::where('ID', $id)->first();
    }

    function findBranchByCodeNumber($code)
    {
        return $this->branchEntity::where('CODE', $code)->first();
    }

    function findBranchByName($name)
    {
        return $this->branchEntity::where('NAME', $name)->first();
    }

    function getMaxCodeBranch()
    {
        return $this->branchEntity::max('CODE');
    }

    function create($request)
    {
        $getCode = $request->CODE;
        $getName = $request->NAME;

        $branchByCode = $this->findBranchByCodeNumber($getCode);

        if ($branchByCode) {
            throw new Exception("Code Branch Is Exist", 404);
        }

        $branchByName = $this->findBranchByName($getName);

        if ($branchByName) {
            throw new Exception("Code Name Is Exist", 404);
        }

        $data = [
            'CODE' => $this->branchCode() ?? '',
            'CODE_NUMBER' => strtoupper($request->CODE) ?? '',
            'NAME' => $request->NAME ?? '',
            'ADDRESS' => $request->ADDRESS ?? '',
            'RT' => $request->RT ?? '',
            'RW' => $request->RW ?? '',
            'PROVINCE' => $request->PROVINCE ?? '',
            'CITY' => $request->CITY ?? '',
            'KELURAHAN' => $request->KELURAHAN ?? '',
            'KECAMATAN' => $request->KECAMATAN ?? '',
            'ZIP_CODE' => $request->ZIP_CODE ?? '',
            'LOCATION' => $request->LOCATION ?? '',
            'PHONE_1' => $request->PHONE_1 ?? '',
            'PHONE_2' => $request->PHONE_2 ?? '',
            'PHONE_3' => $request->PHONE_3 ?? '',
            'DESCR' => $request->DESCR ?? '',
            'STATUS' => 'Active',
            'CREATE_DATE' => Carbon::now()->format('Y-m-d') ?? null,
            'CREATE_USER' => $request->user()->id ?? '',
        ];

        $existingBranch = $this->findBranchByCodeNumber($data['CODE_NUMBER']);

        if ($existingBranch) {
            throw new Exception('Branch with this code already exists.', 400);
        }

        return $this->branchEntity::create($data);
    }

    function update($request, $branchId)
    {
        $getCode = $request->CODE;
        $getName = $request->NAME;

        $branchById = $this->findBranchById($branchId);

        if (!$branchById) {
            throw new Exception("Branch Id Not Found", 404);
        }

        $branchByCode = $this->findBranchByCodeNumber($getCode);

        if ($branchByCode && $branchByCode->ID != $branchId) {
            throw new Exception("Code Branch Is Exist", 404);
        }

        $branchByName = $this->findBranchByName($getName);

        if ($branchByName && $branchByName->ID != $branchId) {
            throw new Exception("Code Name Is Exist", 404);
        }

        $data = [
            'CODE_NUMBER' => strtoupper($request->CODE) ?? '',
            'NAME' => $request->NAME ?? '',
            'ADDRESS' => $request->ADDRESS ?? '',
            'RT' => $request->RT ?? '',
            'RW' => $request->RW ?? '',
            'PROVINCE' => $request->PROVINCE ?? '',
            'CITY' => $request->CITY ?? '',
            'KELURAHAN' => $request->KELURAHAN ?? '',
            'KECAMATAN' => $request->KECAMATAN ?? '',
            'ZIP_CODE' => $request->ZIP_CODE ?? '',
            'LOCATION' => $request->LOCATION ?? '',
            'PHONE_1' => $request->PHONE_1 ?? '',
            'PHONE_2' => $request->PHONE_2 ?? '',
            'PHONE_3' => $request->PHONE_3 ?? '',
            'DESCR' => $request->DESCR ?? '',
            'STATUS' => 'Active',
            'MOD_DATE' => Carbon::now()->format('Y-m-d') ?? null,
            'MOD_USER' => $request->user()->id ?? '',
        ];

        return $branchById->update($data);
    }

    function delete($request, $branchId)
    {
        $branchById = $this->findBranchById($branchId);

        if (!$branchById) {
            throw new Exception("Branch Id Not Found", 404);
        }

        $data = [
            'DELETED_AT' => Carbon::now()->format('Y-m-d') ?? null,
            'DELETED_BY' => $request->user()->id ?? '',
        ];

        return $branchById->update($data);
    }

    function branchCode()
    {
        $maxCode = $this->getMaxCodeBranch();

        if ($maxCode) {
            $lastCodeNumber = intval($maxCode);
            $newCodeNumber = $lastCodeNumber + 1;
            $newCode = str_pad($newCodeNumber, 3, '0', STR_PAD_LEFT);
        } else {
            $newCode = '001';
        }

        return $newCode;
    }
}

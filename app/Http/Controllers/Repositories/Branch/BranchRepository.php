<?php

namespace App\Http\Controllers\Repositories\Branch;

use App\Http\Controllers\Component\GeneratedCode;
use App\Models\M_Branch;
use Carbon\Carbon;
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

    function findBranchByCode($code)
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
        $data = [
            'CODE' => $this->branchCode() ?? '',
            'CODE' => 013,
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
            'CREATE_DATE' => Carbon::now() ?? null,
            'CREATE_USER' => $request->user()->id ?? '',
        ];

        return $this->branchEntity::create($data);
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

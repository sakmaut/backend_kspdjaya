<?php

namespace App\Http\Controllers\Saving\Service;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Saving\Repository\R_Customers;
use Exception;
use Illuminate\Http\Request;

class S_Customers
{
    protected $repository;

    function __construct(R_Customers $repository)
    {
        $this->repository = $repository;
    }

    public function getAllCustomer()
    {
        return $this->repository->getAllCustomer();
    }

    public function findById($id)
    {
        $data = $this->repository->findById($id);

        if (!$data) {
            throw new Exception("Data Not Found", 404);
        }

        return $data;
    }

    public function generateCustCode($request)
    {
        $setCustCode = $this->repository->generateCustCode($request);

        if (!$setCustCode) {
            throw new Exception("Failed to generate customer code", 500);
        }

        return $setCustCode;
    }

    public function createOrUpdate($request, $id = false, $type = "create")
    {
        $userId = $request->user()->id;
        $custCode = $this->generateCustCode($request);
        $existing = $id ? $this->findById($id) : null;

        $fields = [
            'CUST_CODE'   => $custCode,
            'NAME'        => $request->nama,
            'ALIAS'       => $request->nama_panggilan,
            'GENDER'      => $request->jenis_kelamin,
            'BIRTHPLACE'  => $request->tempat_lahir,
            'BIRTHDATE'   => $request->tanggal_lahir ?? null,
            'ID_TYPE'     => $request->tipe_identitas,
            'ID_NUMBER'   => $request->no_identitas,
            'KK_NUMBER'   => $request->no_kk,
            'ADDRESS'     => $request->alamat,
            'RT'          => $request->rt,
            'RW'          => $request->rw,
            'PROVINCE'    => $request->provinsi,
            'CITY'        => $request->kota,
            'KECAMATAN'   => $request->kecamatan,
            'KELURAHAN'   => $request->desa,
            'ZIP_CODE'    => $request->kode_pos,
            'MARTIAL_STATUS' => $request->status_kawin,
            'MOTHER_NAME' => $request->nama_ibu,
            'EDUCATION'   => $request->pendidikan,
            'OCCUPATION'  => $request->pekerjaan,
            'PHONE_PERSONAL'  => $request->hp,
            'TYPE_INPUT'  => 'saving',
        ];

        $timestamp = now();

        if ($existing && $type != 'create') {
            $fields['VERSION']  = $existing->version + 1;
            $fields['MOD_DATE'] = $userId;
            $fields['MOD_USER'] = $timestamp;
            $key = $id ? ['ID' => $id] : ['CUST_CODE' => $custCode];
        } else {
            $fields['VERSION']    = 1;
            $fields['CREATE_USER'] = $userId;
            $key = ['ID' => $id];
        }

        return $this->repository->createOrUpdate($fields, $key);
    }
}

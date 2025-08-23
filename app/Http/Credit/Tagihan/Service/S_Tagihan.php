<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Credit\Tagihan\Repository\R_Tagihan;

class S_Tagihan extends R_Tagihan
{
    protected $repository;

    public function __construct(R_Tagihan $repository)
    {
        $this->repository = $repository;
    }

    public function getListTagihan($request)
    {
        $sql = $this->repository->getAllListTagihan($request);
        return $sql;
    }

    public function createTagihan($request, $id = "")
    {
        $savedData = [];

        if (!empty($id)) {
            $this->repository->deleteByUserId($request['user_id']);
        }

        foreach ($request['list_tagihan'] as $item) {
            $detailData = [
                'USER_ID'      => $request['user_id'],
                'LOAN_NUMBER'  => $item['loan_number'],
                'TGL_JTH_TEMPO' => $item['tgl_jth_tmp'],
                'NAMA_CUST'    => $item['nama_cust'],
                'CYCLE_AWAL'   => $item['cycle_awal'],
                'ALAMAT'       => $item['alamat'],
                'CREATED_BY'   => $request->user()->id ?? null,
            ];

            $saved = $this->repository->create($detailData);
            $savedData[] = $saved;
        }

        return $savedData;
    }
}

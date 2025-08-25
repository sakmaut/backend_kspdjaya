<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Repository\R_Tagihan;
use Exception;

class S_Tagihan extends R_Tagihan
{
    protected $repository;

    public function __construct(R_Tagihan $repository)
    {
        $this->repository = $repository;
    }

    private function createAutoCode($table, $field, $prefix)
    {
        $query = $table::max($field);
        $_trans = date("Ymd");

        $prefixLength = strlen($prefix);

        $startPos = $prefixLength + 11;

        $noUrut = !empty($query) ? (int) substr($query, $startPos, 5) : 0;
        $noUrut++;

        $generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05d", $noUrut);

        return $generateCode;
    }

    public function listTagihanByUserId($request)
    {
        $userId = optional($request->user())->id;

        if (!$userId) {
            throw new \Exception("User ID not found.", 500);
        }

        return $this->repository->getListTagihanByUserId($userId);
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
                'NO_SURAT'     => $this->createAutoCode(M_Tagihan::class, 'NO_SURAT', 'STG'),
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

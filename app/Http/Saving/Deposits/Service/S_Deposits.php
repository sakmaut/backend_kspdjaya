<?php

namespace App\Http\Saving\Deposits\Service;

use App\Http\Saving\Deposits\Repository\R_Deposits;
use Carbon\Carbon;

class S_Deposits extends R_Deposits
{
    protected $repository;

    public function __construct(R_Deposits $repository)
    {
        $this->repository = $repository;
    }

    public function getAllDeposits()
    {
        return $this->repository->all();
    }

    public function getDepositByNumber($deposit_number)
    {
        return $this->repository->findDepositoBy($deposit_number);
    }

    public function createDeposit($request)
    {
        $fields = [
            'status' => "active",
            // 'deposit_number' => generateCodeWithPrefix($this->repository->model, 'deposit_number', 'SFD'),
            'deposit_number' =>  $request->no_deposito ?? 0,
            'deposit_holder' => $request->nama_pemilik ?? "",
            'branch' => $request->user()->branch_id,
            'cust_code' => $request->cust_code ?? "",
            'period' => $request->periode ?? 0,
            'roll_over' => $request->rollover ?? "",
            'int_rate' => $request->suku_bunga ?? 0,
            'entry_date' => Carbon::parse($request->tgl_mulai)->format('Y-m-d H:i:s'),
            'mature_date' => Carbon::parse($request->tgl_mulai)->addMonths($request->periode)->format('Y-m-d H:i:s'),
            'deposit_value' => $request->nominal ?? 0,
            'flag_tax' => $request->restitusi_pajak == 'ya' ? 1 : 0,
            'acc_source' => $request->sumber_dana ?? "",
            'acc_source_num' => $request->no_rek_sumber_dana ?? "",
            'acc_source_name' => $request->nama_sumber_dana ?? "",
            'acc_destination' => $request->rekening_tujuan ?? "",
            'acc_destination_num' => $request->no_rek_tujuan ?? "",
            'acc_destination_name' => $request->nama_rek_tujuan ?? "",
            'created_by' => $request->user()->id,
        ];

        return $this->repository->create($fields);
    }
}

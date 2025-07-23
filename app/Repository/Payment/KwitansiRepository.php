<?php

namespace App\Repository\Payment;

use App\Models\M_Kwitansi;


class KwitansiRepository
{
    protected $model;

    function __construct(M_Kwitansi $model)
    {
        $this->model = $model;
    }

    public function getAllOrdered()
    {
        return $this->model::orderBy('CREATED_AT', 'DESC');
    }

    public function getPendingForHO()
    {
        return $this->getAllOrdered()
            ->where('STTS_PAYMENT', 'PENDING')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('METODE_PEMBAYARAN', '!=', 'cash')
                        ->whereIn('PAYMENT_TYPE', ['angsuran', 'pokok_sebagian']);
                })->orWhere(function ($sub) {
                    $sub->where('METODE_PEMBAYARAN', 'cash')
                        ->where('PAYMENT_TYPE', 'pelunasan');
                });
            })->get();
    }

    public function getFilteredForBranch($request, $filters = [], $date = null)
    {
        $branchCode = $request->user()->branch_id;
        $position = $request->user()->position;

        $query = $this->getAllOrdered()->where('BRANCH_CODE', $branchCode);

        foreach ($filters as [$column, $operator, $value]) {
            if ($value && $value !== '%') {
                $query->where($column, $operator, $value);
            }
        }

        if ($date) {
            $query->whereDate('CREATED_AT', $date);
        }

        if ($position === 'HO') {
            $this->getPendingForHO();
        }

        return $query->get();
    }

    public function create($no_inv, $data = [])
    {
        return $this->model::firstOrCreate(
            ['NO_TRANSAKSI' => $no_inv],
            $data
        );
    }
}

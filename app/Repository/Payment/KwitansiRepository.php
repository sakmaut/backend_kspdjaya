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
        return $this->model::orderBy('CREATED_AT', 'DESC')
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

    public function getFilteredForBranch($branchCode, $filters = [], $date = null)
    {
        $query = $this->model::orderBy('CREATED_AT', 'DESC')
            ->where('BRANCH_CODE', $branchCode);

        foreach ($filters as [$column, $operator, $value]) {
            if ($value && $value !== '%') {
                $query->where($column, $operator, $value);
            }
        }

        if ($date) {
            $query->whereDate('CREATED_AT', $date);
        }

        return $query->get();
    }
}

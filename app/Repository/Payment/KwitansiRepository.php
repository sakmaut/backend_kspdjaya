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
        return $this->model::with([
            'branch:ID,NAME',
            'users:id,fullname,username,position',
            'attachment:payment_id,file_attach',
            'print_log:ID,COUNT'
        ])->orderByRaw("CAST(SUBSTRING_INDEX(NO_TRANSAKSI, '-', -1) AS UNSIGNED) DESC");
    }

    public function getPendingForHO($request)
    {
        $cabang     = $request->query('cabang');
        $dari       = $request->query('dari');
        $sampai     = $request->query('sampai');
        $notrx      = $request->query('notrx');
        $nama       = $request->query('nama');
        $noKontrak  = $request->query('no_kontrak');
        $tipe       = $request->query('tipe');
        $pembayaran = $request->query('pembayaran');

        $query = $this->getAllOrdered()
            ->where('STTS_PAYMENT', 'PENDING')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('METODE_PEMBAYARAN', '!=', 'cash')
                        ->whereIn('PAYMENT_TYPE', ['angsuran', 'pokok_sebagian']);
                })->orWhere(function ($sub) {
                    $sub->where('METODE_PEMBAYARAN', 'cash')
                        ->whereIn('PAYMENT_TYPE', ['pelunasan', 'pokok_sebagian']);
                });
            });

        // ✅ Filter tanggal
        if (!empty($dari)) {
            $query->whereDate('CREATED_AT', '>=', $dari);
        }

        if (!empty($sampai)) {
            $query->whereDate('CREATED_AT', '<=', $sampai);
        }

        // ✅ Filter No Transaksi
        if (!empty($notrx)) {
            $query->where('NO_TRX', 'like', "%$notrx%");
        }

        // ✅ Filter Nama
        if (!empty($nama)) {
            $query->where('NAMA', 'like', "%$nama%");
        }

        // ✅ Filter No Kontrak
        if (!empty($noKontrak)) {
            $query->where('NO_KONTRAK', 'like', "%$noKontrak%");
        }

        // ✅ Filter Tipe (PAYMENT_TYPE)
        if (!empty($tipe)) {
            $query->where('PAYMENT_TYPE', $tipe);
        }

        // ✅ Filter Metode Pembayaran
        if (!empty($pembayaran)) {
            $query->where('METODE_PEMBAYARAN', $pembayaran);
        }

        // ✅ Filter Cabang
        if (!empty($cabang) && $cabang !== 'SEMUA CABANG') {
            $query->where('BRANCH_CODE', $cabang);
        }

        return $query->get();
    }

    public function getFilteredForBranch($request,$branchCode, $filters = [], $date = null)
    {
        $user = $request->user();

        $query = $this->getAllOrdered();

        if ($user->position === 'KOLEKTOR') {
            $query->where('CREATED_BY', $user->id);
        } else {
            $query->where('BRANCH_CODE', $branchCode);
        }

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

    public function create($no_inv, $data = [])
    {
        return $this->model::firstOrCreate(
            ['NO_TRANSAKSI' => $no_inv],
            $data
        );
    }
}

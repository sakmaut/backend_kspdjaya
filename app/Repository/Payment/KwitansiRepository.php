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

    private function mapPaymentType($tipe)
    {
        return match ($tipe) {
            'pelunasan' => 'pelunasan',
            'pelunasan_pokok_sebagian' => 'pokok_sebagian',
            'pokok_sebagian' => 'pokok_sebagian',
            'angsuran' => 'angsuran',
            default => null,
        };
    }

    private function hasFilter($request)
    {
        return
            $request->query('cabang') && $request->query('cabang') !== 'SEMUA CABANG' ||
            $request->query('dari') ||
            $request->query('sampai') ||
            $request->query('notrx') ||
            $request->query('nama') ||
            $request->query('no_kontrak') ||
            $request->query('tipe') ||
            $request->query('pembayaran');
    }

    public function getAllOrdered()
    {
        return $this->model::with([
            'branch:ID,NAME',
            'users:id,fullname,username,position',
            'attachment:payment_id,file_attach',
            'print_log:ID,COUNT',
            'kwitansi_structur_detail',
            'kwitansi_pelunasan_detail'
        ])->orderByRaw("CAST(SUBSTRING_INDEX(NO_TRANSAKSI, '-', -1) AS UNSIGNED) DESC");
    }

    // public function basePendingHO()
    // {
    //     return $this->getAllOrdered()
    //         ->where('STTS_PAYMENT', 'PENDING')
    //         ->where(function ($q) {
    //             $q->where(function ($sub) {
    //                 $sub->where('METODE_PEMBAYARAN', '!=', 'cash')
    //                     ->whereIn('PAYMENT_TYPE', ['angsuran', 'pokok_sebagian']);
    //             })->orWhere(function ($sub) {
    //                 $sub->whereIn('METODE_PEMBAYARAN', ['cash', 'transfer'])
    //                     ->whereIn('PAYMENT_TYPE', ['pelunasan','angsuran', 'pokok_sebagian']);
    //             });
    //         });
    // }

    public function basePendingHO($request)
    {
        $query = $this->getAllOrdered();

        if (!$this->hasFilter($request)) {
            $query->where('STTS_PAYMENT', 'PENDING');
        }

        $query->where(function ($q) {

            $q->where(function ($sub) {
                $sub->whereIn('METODE_PEMBAYARAN', ['transfer'])
                    ->whereIn('PAYMENT_TYPE', ['angsuran', 'pokok_sebagian']);
            })

                ->orWhere(function ($sub) {
                    $sub->whereIn('METODE_PEMBAYARAN', ['cash', 'transfer'])
                        ->whereIn('PAYMENT_TYPE', ['pelunasan', 'pokok_sebagian']);
                });
        });

        return $query;
    }

    public function applyFilterPendingHO($query, $request)
    {
        $cabang     = $request->query('cabang');
        $dari       = $request->query('dari');
        $sampai     = $request->query('sampai');
        $notrx      = $request->query('notrx');
        $nama       = $request->query('nama');
        $noKontrak  = $request->query('no_kontrak');
        $tipe       = $this->mapPaymentType($request->query('tipe'));
        $pembayaran = $request->query('pembayaran');

        if ($dari && $sampai) {
            $query->whereBetween('CREATED_AT', [$dari, $sampai]);
        } elseif ($dari) {
            $query->whereDate('CREATED_AT', '>=', $dari);
        } elseif ($sampai) {
            $query->whereDate('CREATED_AT', '<=', $sampai);
        }

        // ✅ no transaksi (FIX: pakai NO_TRANSAKSI)
        $query->when($notrx, fn($q) => $q->where('NO_TRANSAKSI', 'like', "%$notrx%"));

        // ✅ no kontrak
        $query->when($noKontrak, fn($q) => $q->where('NO_KONTRAK', 'like', "%$noKontrak%"));

        // ✅ tipe
        $query->when($tipe, fn($q) => $q->where('PAYMENT_TYPE', $tipe));

        // ✅ metode pembayaran
        $query->when($pembayaran, fn($q) => $q->where('METODE_PEMBAYARAN', $pembayaran));

        // ✅ cabang
        $query->when($cabang && $cabang !== 'SEMUA CABANG', fn($q) => 
            $q->where('BRANCH_CODE', $cabang)
        );

        // ✅ nama (dari relasi users!)
        $query->when($nama, function ($q) use ($nama) {
            $q->whereHas('users', function ($sub) use ($nama) {
                $sub->where('fullname', 'like', "%$nama%");
            });
        });

        return $query->get();
    }

    public function getPendingForHO($request)
    {
        $query = $this->basePendingHO($request);
        return $this->applyFilterPendingHO($query, $request);
    }

    public function getFilteredForBranch($request, $user, $filters = [], $date = null, $nama = null, $cabang = null)
    {
        $query = $this->getAllOrdered();

        // ✅ ROLE HANDLING
        if ($user->position === 'HO') {
            // HO bisa semua cabang
            if (!empty($cabang) && $cabang !== 'SEMUA CABANG') {
                $query->where('BRANCH_CODE', $cabang);
            }
        } 
        // elseif ($user->position === 'KOLEKTOR') {
        //     $query->where('CREATED_BY', $user->id);
        // } 
        else {
            $query->where('BRANCH_CODE', $user->branch_id);
        }

        // ✅ FILTER UMUM
        foreach ($filters as [$column, $operator, $value]) {
            if (!empty($value) && $value !== '%') {
                $query->where($column, $operator, $value);
            }
        }

        // ✅ FILTER NAMA (relasi users)
        if (!empty($nama)) {
            $query->whereHas('users', function ($q) use ($nama) {
                $q->where('fullname', 'like', "%$nama%");
            });
        }

        // ✅ FILTER TANGGAL
        if ($date) {
            $query->whereDate('CREATED_AT', $date);
        }

        return $query->get();
    }

    // public function getFilteredForBranch($request,$branchCode, $filters = [], $date = null)
    // {
    //     $user = $request->user();

    //     $query = $this->getAllOrdered();

    //     if ($user->position === 'KOLEKTOR') {
    //         $query->where('CREATED_BY', $user->id);
    //     } else {
    //         $query->where('BRANCH_CODE', $branchCode);
    //     }

    //     foreach ($filters as [$column, $operator, $value]) {
    //         if ($value && $value !== '%') {
    //             $query->where($column, $operator, $value);
    //         }
    //     }

    //     if ($date) {
    //         $query->whereDate('CREATED_AT', $date);
    //     }

    //     return $query->get();
    // }

    public function create($no_inv, $data = [])
    {
        return $this->model::firstOrCreate(
            ['NO_TRANSAKSI' => $no_inv],
            $data
        );
    }
}

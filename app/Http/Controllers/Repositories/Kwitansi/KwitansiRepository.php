<?php

namespace App\Http\Controllers\Repositories\Kwitansi;

use App\Models\M_Kwitansi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KwitansiRepository implements KwitansiRepositoryInterface
{
    protected $kwitansiEntity;

    function __construct(M_Kwitansi $kwitansiEntity)
    {
        $this->kwitansiEntity = $kwitansiEntity;
    }

    function getAllDataKwitansi($request)
    {
        $notrx = $request->query('notrx');
        $nama = $request->query('nama');
        $no_kontrak = $request->query('no_kontrak');
        $tipe = $request->query('tipe');
        $dari = $request->query('dari');

        $getPosition = $request->user()->position;
        $getBranch = $request->user()->branch_id;

        $data = $this->kwitansiEntity->orderBy('CREATED_AT', 'DESC');

        if (strtolower($getPosition) == 'ho') {
            return $data->where('STTS_PAYMENT', 'PENDING')->get();
        }

        $data->where('BRANCH_CODE', $getBranch);

        if ($tipe) {
            $data->where('PAYMENT_TYPE', $tipe == 'pelunasan' ? 'pelunasan' : '!=', 'pelunasan');
        }

        if ($notrx) {
            $data->where('NO_TRANSAKSI', $notrx);
        }

        if ($nama) {
            $data->where('NAMA', 'like', '%' . $nama . '%');
        }

        if ($no_kontrak) {
            $data->where('LOAN_NUMBER', $no_kontrak);
        }

        if ($dari && $dari != 'null') {
            $data->whereDate('CREATED_AT', Carbon::parse($dari)->toDateString());
        } elseif (empty($notrx) && empty($nama) && empty($no_kontrak)) {
            $data->whereDate('CREATED_AT', Carbon::today()->toDateString());
        }

        return $data->get();
    }
}

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

        $data = M_Kwitansi::orderBy('CREATED_AT', 'DESC');

        if (strtolower($getPosition) == 'ho') {
            $data->where('STTS_PAYMENT', '=', 'PENDING');
            // $data->whereIn('STTS_PAYMENT', ['PENDING', 'PAID']);
            // $data->where('METODE_PEMBAYARAN', '=', 'transfer');
            // $data->whereDate('created_at', Carbon::today());
        } else {
            $data->where('BRANCH_CODE', '=', $getBranch);

            switch ($tipe) {
                case 'pembayaran':
                    $data->where('PAYMENT_TYPE', '!=', 'pelunasan');
                    break;
                case 'pelunasan':
                    $data->where('PAYMENT_TYPE', 'pelunasan');
                    break;
            }

            if (empty($notrx) && empty($nama) && empty($no_kontrak) && (empty($dari) || $dari == 'null')) {
                $data->where(DB::raw('DATE_FORMAT(CREATED_AT,"%Y%m%d")'), Carbon::now()->format('Ymd'));
            } else {

                if (!empty($notrx)) {
                    $data->where('NO_TRANSAKSI', $notrx);
                }

                if (!empty($nama)) {
                    $data->where('NAMA', 'like', '%' . $nama . '%');
                }

                if (!empty($no_kontrak)) {
                    $data->where('LOAN_NUMBER', $no_kontrak);
                }

                if ($dari != 'null') {
                    $formattedDate = Carbon::parse($dari)->format('Ymd');
                    $data->where(DB::raw('DATE_FORMAT(CREATED_AT,"%Y%m%d")'), $formattedDate);
                }
            }
        }

        $results = $data->get();

        return $results;
    }
}

<?php

namespace App\Http\Controllers\Repositories\Kwitansi;

use App\Models\M_Kwitansi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KwitansiRepository
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

    function create($request,$noInvoice,$customerDetail){
        $cekPaymentMethod = $request->payment_method == 'cash' && strtolower($request->bayar_dengan_diskon) != 'ya';

        //  "STTS_PAYMENT" => $cekPaymentMethod && !$this->checkPosition($request) ? "PAID" : "PENDING",

        $save_kwitansi = [
            "PAYMENT_TYPE" => 'angsuran',
            "PAYMENT_ID" => $request->uid,
            "STTS_PAYMENT" => $cekPaymentMethod ? "PAID" : "PENDING",
            "NO_TRANSAKSI" => $noInvoice,
            "LOAN_NUMBER" => $request->no_facility ?? null,
            "TGL_TRANSAKSI" => Carbon::now()->format('d-m-Y'),
            'BRANCH_CODE' => $request->user()->branch_id,
            'CUST_CODE' => $customerDetail['cust_code'] ?? '',
            'NAMA' => $customerDetail['nama'] ?? '',
            'ALAMAT' => $customerDetail['alamat'] ?? '',
            'RT' => $customerDetail['rt'] ?? '',
            'RW' => $customerDetail['rw'] ?? '',
            'PROVINSI' => $customerDetail['provinsi'] ?? '',
            'KOTA' => $customerDetail['kota'] ?? '',
            'KELURAHAN' => $customerDetail['kelurahan'] ?? '',
            'KECAMATAN' => $customerDetail['kecamatan'] ?? '',
            "METODE_PEMBAYARAN" => $request->payment_method ?? null,
            "TOTAL_BAYAR" => $request->total_bayar ?? null,
            "DISKON" => $request->diskon_tunggakan ?? null,
            "DISKON_FLAG" => $request->bayar_dengan_diskon ?? null,
            "PEMBULATAN" => $request->pembulatan ?? null,
            "KEMBALIAN" => $request->kembalian ?? null,
            "JUMLAH_UANG" => $request->jumlah_uang ?? null,
            "NAMA_BANK" => $request->nama_bank ?? null,
            "NO_REKENING" => $request->no_rekening ?? null,
            "CREATED_BY" => $request->user()->id,
            "CREATED_AT" => Carbon::now()
        ];

        M_Kwitansi::firstOrCreate(
            ['NO_TRANSAKSI' => $noInvoice],
            $save_kwitansi
        );
    }
}

<?php

namespace App\Services\Kwitansi;

use App\Http\Controllers\Enum\UserPosition\UserPositionEnum;
use App\Repository\Payment\KwitansiRepository;
use App\Services\Credit\CreditService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;

class KwitansiService
{
    protected $kwitansiRepository;
    protected $creditService;
    protected $userPositionEnum;
    protected $uuid;

    function __construct(
        KwitansiRepository $kwitansiRepository,
        CreditService $creditService,
        UserPositionEnum $userPositionEnum
    ) {
        $this->kwitansiRepository = $kwitansiRepository;
        $this->creditService = $creditService;
        $this->userPositionEnum = $userPositionEnum;
        $this->uuid = Uuid::uuid7()->toString();
    }

    public function getKwitansiPayment($request)
    {
        $user = $request->user();

        $tipe = $request->query('tipe');

        if ($tipe === 'pelunasan') {
            $paymentType = 'pelunasan';
        } elseif ($tipe === 'pelunasan_pokok_sebagian') {
            $paymentType = 'pokok_sebagian';
        } else {
            $paymentType = 'angsuran';
        }

        $filters = [
            ['PAYMENT_TYPE', '=', $paymentType],
            ['NO_TRANSAKSI', '=', $request->query('notrx')],
            ['NAMA', 'like', '%' . $request->query('nama') . '%'],
            ['LOAN_NUMBER', '=', $request->query('no_kontrak')],
        ];

        $dari = $request->query('dari');
        $dateFilter = null;

        if ($dari && $dari !== 'null') {
            $dateFilter = Carbon::parse($dari)->toDateString();
        } elseif (
            blank($request->query('notrx')) &&
            blank($request->query('nama')) &&
            blank($request->query('no_kontrak'))
        ) {
            $dateFilter = Carbon::today()->toDateString();
        }

        return $this->kwitansiRepository->getFilteredForBranch($user->branch_id, $filters, $dateFilter);
    }

    public function create($request, $tipe = 'angsuran')
    {
        $getLoanNumber = $request->LOAN_NUMBER;
        $customer = $this->creditService->getCreditWithCustomer($getLoanNumber);

        if (empty($getLoanNumber) || !$customer || empty($customer->customer)) {
            throw new Exception("Data Not Found", 404);
        }

        $no_inv = generateCodeKwitansi($request, 'kwitansi', 'NO_TRANSAKSI', 'INV');
        $status = strtolower($request->METODE_PEMBAYARAN) === 'cash' ? "PAID" : 'PENDING';

        $data = [
            "PAYMENT_TYPE" => $tipe,
            "PAYMENT_ID" => $idGenerate ?? '',
            "STTS_PAYMENT" => $status,
            "NO_TRANSAKSI" => $no_inv,
            "LOAN_NUMBER" => $request->LOAN_NUMBER,
            "TGL_TRANSAKSI" => Carbon::now(),
            "CUST_CODE" => $customer->CUST_CODE,
            "BRANCH_CODE" => $request->user()->branch_id,
            "NAMA" => $customer->customer['NAME'],
            "ALAMAT" => $customer->customer['ADDRESS'],
            "RT" => $customer->customer['RT'],
            "RW" => $customer->customer['RW'],
            "PROVINSI" => $customer->customer['PROVINCE'],
            "KOTA" => $customer->customer['CITY'],
            "KECAMATAN" => $customer->customer['KECAMATAN'],
            "KELURAHAN" => $customer->customer['KELURAHAN'],
            "METODE_PEMBAYARAN" => $request->METODE_PEMBAYARAN,
            "TOTAL_BAYAR" => $request->TOTAL_BAYAR ?? 0,
            "PINALTY_PELUNASAN" => 0,
            "DISKON_PINALTY_PELUNASAN" => 0,
            "PEMBULATAN" => $request->PEMBULATAN ?? 0,
            "DISKON" => $request->JUMLAH_DISKON ?? 0,
            "KEMBALIAN" => $request->KEMBALIAN ?? 0,
            "JUMLAH_UANG" => $request->UANG_PELANGGAN,
            "NAMA_BANK" => $request->NAMA_BANK,
            "NO_REKENING" => $request->NO_REKENING,
            "CREATED_BY" => $request->user()->id
        ];

        return $this->kwitansiRepository->create($no_inv, $data);
    }
}

<?php

namespace App\Http\Controllers\Transaction;

class TransactionServices
{
    protected TransactionRepository $repository;

    public function __construct(TransactionRepository $repository) {
        $this->repository = $repository;
    }

    public function create($request)
    {
        $fields = [
            'ID',
            'NO_INVOICE',
            'TIPE',
            'STATUS',
            'METODE',
            'ID_CABANG',
            'LOAN_NUMBER',
            'CUST_CODE',
            'PEMBULATAN',
            'DISKON',
            'FLAG_DISKON',
            'KEMBALIAN',
            'JUMLAH_UANG',
            'TOTAL_BAYAR',
            'BAYAR_BUNGA',
            'BAYAR_PINALTI',
            'BAYAR_POKOK',
            'BAYAR_DENDA',
            'DISKON_POKOK',
            'DISKON_BUNGA',
            'DISKON_PINALTI',
            'DISKON_DENDA',
            'NAMA_BANK',
            'NOMOR_REKENING',
            'BUKTI_TRANSFER',
            'CREATED_BY',
            'CREATED_AT'
        ];
        
       $execute =  $this->repository->create($fields);
       return $execute;
    } 
}

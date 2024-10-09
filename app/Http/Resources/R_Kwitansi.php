<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Kwitansi extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Retrieve payment and related data
        $payment = M_Payment::where('INVOICE', $this->NO_TRANSAKSI)->first();
        $details = M_KwitansiStructurDetail::where('no_invoice', $this->NO_TRANSAKSI)
            ->orderBy('angsuran_ke', 'asc')
            ->get();
        $branch = $payment ? M_Branch::where('CODE_NUMBER', $payment->BRANCH)->first() : null;
        $attachment = $payment ? M_PaymentAttachment::where('payment_id', $payment->NO_TRX)->value('file_attach') : null;

        return [
            "id" => $this->ID,
            "no_transaksi" => $this->NO_TRANSAKSI,
            "no_fasilitas" => $this->LOAN_NUMBER,
            "cabang" => $branch->NAME ?? null,
            "cust_code" => $this->CUST_CODE,
            "nama" => $this->NAMA,
            "alamat" => $this->ALAMAT,
            "rt" => $this->RT,
            "rw" => $this->RW,
            "provinsi" => $this->PROVINSI,
            "kota" => $this->KOTA,
            "kelurahan" => $this->KELURAHAN,
            "kecamatan" => $this->KECAMATAN,
            "tgl_transaksi" => $this->TGL_TRANSAKSI,
            "payment_method" => $this->METODE_PEMBAYARAN,
            "nama_bank" => $this->NAMA_BANK,
            "no_rekening" => $this->NO_REKENING,
            "bukti_transfer" => $this->BUKTI_TRANSFER,
            'installment' => $details->pluck('angsuran_ke')->implode(','),
            'bayar_angsuran' => $details->sum('bayar_angsuran'),
            'bayar_denda' => $details->sum('bayar_denda'),
            "pembulatan" => intval($this->PEMBULATAN),
            "kembalian" => intval($this->KEMBALIAN),
            "total_bayar" => intval($this->TOTAL_BAYAR),
            "jumlah_uang" => intval($this->JUMLAH_UANG),
            "terbilang" => bilangan($this->TOTAL_BAYAR) ?? null,
            'attachment' => $attachment,
            "STATUS" => $this->STTS_PAYMENT ?? null,
            "created_by" => $this->CREATED_BY,
            "created_at" => $this->CREATED_AT,
        ];
    }

}

<?php

namespace App\Http\Resources;

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

        $payment = M_Payment::where('INVOICE', $this->NO_TRANSAKSI)->limit(1)->get()->first();
        
        $detail = M_KwitansiStructurDetail::where('no_invoice',$this->NO_TRANSAKSI)->orderBy('angsuran_ke', 'asc')->get();

        if ($payment) {
            $attachment = M_PaymentAttachment::where('payment_id', $payment->NO_TRX??null)->get();
        }else{
            $attachment = '';
        }

        $pembayaran = []; // Initialize an empty array to store the pembayaran data

        foreach ($detail as $res) {
            $pembayaran[] = [
                'installment' => $res->angsuran_ke, // Use $res instead of $detail
                'title' => 'Angsuran Ke-' . $res->angsuran_ke
            ];
        }

        return [
            "id" => $this->ID,
            "no_transaksi" => $this->NO_TRANSAKSI,
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
            "pembayaran" => $pembayaran,
            "pembulatan" => intval($this->PEMBULATAN),
            "kembalian" => intval($this->KEMBALIAN),
            "total_bayar" => intval($this->TOTAL_BAYAR),
            "jumlah_uang" => intval($this->JUMLAH_UANG),
            "terbilang" => bilangan($this->TOTAL_BAYAR) ?? null,
            "attachment" => $attachment,
            "STATUS" => $payment->STTS_RCRD,
            "created_by" =>  $this->CREATED_BY,
            "created_at" => $this->CREATED_AT
        ];
    }
}

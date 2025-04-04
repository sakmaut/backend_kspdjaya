<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Pelunasan extends JsonResource
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

        // Build pembayaran array
        $pembayaran = $details->map(function ($res) {
            return [
                'installment' => $res->angsuran_ke,
                'title' => 'Angsuran Ke-' . $res->angsuran_ke,
                'bayar_angsuran' => $res->bayar_angsuran,
                'bayar_denda' => $res->bayar_denda,
            ];
        })->toArray();

        $getUser = User::find($this->CREATED_BY);

        return [
            "id" => $this->ID,
            "payment_id" => $this->PAYMENT_ID,
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
            "payment_method" => $this->METODE_PEMBAYARAN??'',
            "nama_bank" => $this->NAMA_BANK,
            "no_rekening" => $this->NO_REKENING,
            "bukti_transfer" => $this->BUKTI_TRANSFER,
            "pembayaran" => $pembayaran,
            "pembulatan" => intval($this->PEMBULATAN),
            "kembalian" => intval($this->KEMBALIAN),
            "total_bayar" => intval($this->TOTAL_BAYAR),
            "jumlah_uang" => intval($this->JUMLAH_UANG),
            "terbilang" => bilangan($this->TOTAL_BAYAR) ?? null,
            'attachment' => $attachment,
            "STATUS" => $payment->STTS_RCRD ?? null,
            "created_by" => $getUser ? $getUser->fullname ?? $getUser->username : $this->CREATED_BY ?? '',
            "created_at" => $this->CREATED_AT,
        ];
    }

}

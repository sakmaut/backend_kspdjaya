<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_Customer;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LogPrint;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_Kwitansi extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = M_KwitansiStructurDetail::select('id', 
                                                    'no_invoice', 
                                                    'key',
                                                    'angsuran_ke', 
                                                    'loan_number', 
                                                    'tgl_angsuran', 
                                                    'principal', 
                                                    'interest', 
                                                    'installment', 
                                                    'principal_remains',
                                                    'payment', 
                                                    'bayar_angsuran',
                                                    DB::raw('CASE WHEN bayar_denda = 0 THEN 0 ELSE COALESCE(bayar_denda, 0) END as bayar_denda'),
                                                    'total_bayar',
                                                    'flag', 
                                                    'denda',
                                                    'diskon_denda')
                                            ->where('no_invoice', $this->NO_TRANSAKSI)
                                            ->where(function($query) {
                                                $query->where('installment', '<>', 0)
                                                    ->orWhere('bayar_denda', '<>', 0)
                                                    ->orWhere('diskon_denda', '=', 1);
                                            })
                                            ->orderByRaw('CAST(angsuran_ke AS SIGNED) ASC')
                                            ->get();
                                        
        

        $branch = M_Branch::where('ID', $this->BRANCH_CODE)->first();
        $attachment = M_PaymentAttachment::where('payment_id', $this->PAYMENT_ID)->value('file_attach') ?? null;
        $logPrint = M_LogPrint::where('ID', $this->NO_TRANSAKSI)->first();

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
            "tgl_transaksi" => Carbon::parse($this->CREATED_AT)->setTimezone('Asia/Jakarta')->format('d-m-Y H:i:s'),
            "payment_method" => $this->METODE_PEMBAYARAN,
            "nama_bank" => $this->NAMA_BANK,
            "no_rekening" => $this->NO_REKENING,
            "bukti_transfer" => $this->BUKTI_TRANSFER,
            'installment' => $details->pluck('angsuran_ke')->implode(','),
            'bayar_angsuran' => $details->sum(fn($item) => (float) $item->bayar_angsuran) ?: 0,
            'bayar_denda' => $details->sum(fn($item) => (float) $item->bayar_denda) ?: 0,
            "pembulatan" => intval($this->PEMBULATAN ?? 0),
            "kembalian" => intval($this->KEMBALIAN ?? 0),
            "total_bayar" => intval($this->TOTAL_BAYAR ?? 0),
            "jumlah_uang" => intval($this->JUMLAH_UANG ?? 0),
            "terbilang" => bilangan($this->TOTAL_BAYAR) ?? null,
            'attachment' => $attachment,
            'struktur' => $details,
            "STATUS" => $this->STTS_PAYMENT ?? null,
            "created_by" => $this->CREATED_BY,
            "print_ke" =>  intval($logPrint->COUNT??0),
        ];
    }

}

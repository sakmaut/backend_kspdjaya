<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LogPrint;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_KwitansiPelunasan extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = DB::table('payment as a')
                        ->leftJoin('payment_detail as b', 'b.PAYMENT_ID', '=', 'a.ID')
                        ->where('a.INVOICE', '=', $this->NO_TRANSAKSI)
                        ->select('a.TITLE','a.START_DATE', 'b.ACC_KEYS', 'b.ORIGINAL_AMOUNT')
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
            "tgl_transaksi" => Carbon::parse($this->CREATED_AT)->format('d-m-Y H:i:s'),
            "payment_method" => $this->METODE_PEMBAYARAN,
            "nama_bank" => $this->NAMA_BANK,
            "no_rekening" => $this->NO_REKENING,
            "bukti_transfer" => $this->BUKTI_TRANSFER,
            'bayar_angsuran' => 'PELUNASAN',
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

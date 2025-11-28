<?php

namespace App\Http\Saving\Deposits\Resource;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_DepositDetails extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current_date = Carbon::now();
        $day_calc = $current_date->diffInDays($this->created_at);
        $bunga_kotor = $this->deposit_value * ($this->int_rate / 100) * ($day_calc / 365);
        $pajak = $bunga_kotor * 0.20;
        $bunga_bersih = $bunga_kotor - $pajak;

        return [
            "id" => $this->id,
            "no_rekening" => $this->acc_destination,
            "no_deposito" => $this->deposit_number,
            "nama_nasabah" => $this->deposit_holder,
            "alamat" => $this->customer->ADDRESS . ', RT ' . $this->customer->RT . '/RW ' . $this->customer->RW . ', ' .
                $this->customer->KELURAHAN . ', ' . $this->customer->KECAMATAN . ', ' .
                $this->customer->CITY . ', ' . $this->customer->PROVINCE . ' ' . $this->customer->ZIP_CODE,
            "suku_bunga" => (float) $this->int_rate,
            "nilai_bunga" => (int) $bunga_kotor,
            "pajak" => (int) $pajak,
            "bunga_pajak" => (int) $bunga_bersih,
            "hari_aktif" => $day_calc,
            "status" => $this->status,
            "tgl_mulai" => $this->created_at,
            "jangka_waktu" => $this->deposit_holder,
            "tanggal_valuta" => Carbon::parse($this->entry_date)->format('d-m-Y'),
            "tanggal_jth_tmpo" => Carbon::parse($this->mature_date)->format('d-m-Y'),
            "jumlah_pokok" => (float) $this->deposit_value,
            "status" => $this->status,
            "tempo" => (int) $this->period,
            "roll_over" => $this->roll_over,
            "sumber_dana" => $this->acc_source,
            "rekening_tujuan" => $this->acc_destination,

            // Hanya tampil jika bukan "lainnya"
            "no_rek_sumber_dana" => $this->acc_source == 'Lainnya' ? $this->acc_source_num ?? '' : $this->account_source->acc_number ?? '',
            "nama_sumber_dana" => $this->acc_source == 'Lainnya' ? $this->acc_source_name : $this->account_source->acc_name ?? '',

            "no_rek_tujuan" =>  $this->acc_destination == 'Lainnya' ? $this->acc_destination_num ?? '' : $this->account_destination->acc_number ?? '',
            "nama_rek_tujuan" =>  $this->acc_destination == 'Lainnya' ? $this->acc_destination_name ?? '' : $this->account_destination->acc_name ?? '',
        ];
    }
}

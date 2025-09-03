<?php

namespace App\Http\Saving\Deposits\Resource;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_DepositDetails extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "no_rekening" => $this->acc_destination,
            "no_deposito" => $this->deposit_number,
            "nama_nasabah" => $this->deposit_holder,
            "alamat" => $this->customer->ADDRESS . ', RT ' . $this->customer->RT . '/RW ' . $this->customer->RW . ', ' .
                $this->customer->KELURAHAN . ', ' . $this->customer->KECAMATAN . ', ' .
                $this->customer->CITY . ', ' . $this->customer->PROVINCE . ' ' . $this->customer->ZIP_CODE,
            "suku_bunga" => $this->int_rate,
            "jangka_waktu" => $this->deposit_holder,
            "tanggal_valuta" => Carbon::parse($this->entry_date)->format('d-m-Y'),
            "tanggal_jth_tmpo" => Carbon::parse($this->mature_date)->format('d-m-Y'),
            "jumlah_pokok" => (float) $this->deposit_value,
            "status" => $this->status,
            "tempo" => $this->period,
            "roll_over" => $this->roll_over
        ];
    }
}

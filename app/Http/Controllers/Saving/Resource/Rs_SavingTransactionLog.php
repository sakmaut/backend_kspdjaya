<?php

namespace App\Http\Controllers\Saving\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_SavingTransactionLog extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "no_rekening" => $this->acc_number,
            "nama_pemilik" => $this->customer->NAME,
            "nama_alias" => $this->customer->ALIAS,
            "nama_ibu_kandung" =>  $this->customer->MOTHER_NAME,
            "jenis_kelamin" =>  $this->customer->GENDER,
            "tempat_lahir" =>  $this->customer->BIRTHPLACE,
            "tanggal_lahir" =>  $this->customer->BIRTHDATE,
            "alamat" =>  $this->customer->ADDRESS,
            "telepon" => $this->customer->PHONE_PERSONAL,
            "email" =>  $this->customer->EMAIL ?? "",
            "tipe_identitas" =>  $this->customer->ID_TYPE,
            "no_identitas" =>  $this->customer->ID_NUMBER,
            "cabang" => "",
            "jenis_tabungan" => $this->product_saving->product_type,
            "kode_jenis_tabungan" => $this->product_saving->product_code,
            "saldo" => floatval($this->min_bal),
            "status" => "",
            "tgl_registrasi" => $this->created_at,
            "tgl_terakhir_transaksi" => $this->date_last_trans,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Branch extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ID,
            'kode' => $this->CODE,
            'nama' => $this->NAME,
            'alamat' => $this->ADDRESS,
            'rt/rw' => $this->RT.'/'.$this->RW,
            'provinsi' => $this->PROVINCE,
            'kota' => $this->CITY,
            'kelurahan' => $this->KELURAHAN,
            'kecamatan' => $this->KECAMATAN,
            'kode_pos' => $this->ZIP_CODE,
            'lokasi' => $this->LOCATION,
            'telp_1' => $this->PHONE_1,
            'telp_2' => $this->PHONE_2,
            'telp_3' => $this->PHONE_3,
            'keterangan' => $this->DESCR,
            'status' => $this->STATUS,
            'tanggal_buat' => $this->CREATE_DATE,
            'dibuat_oleh' => $this->CREATE_USER
        ];
    }
}

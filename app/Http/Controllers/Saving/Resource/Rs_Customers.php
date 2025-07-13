<?php

namespace App\Http\Controllers\Saving\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_Customers extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cust_code'       => $this->CUST_CODE,
            'nama'            => $this->NAME,
            'nama_panggilan'  => $this->ALIAS,
            'jenis_kelamin'   => $this->GENDER,
            'tempat_lahir'    => $this->BIRTHPLACE,
            'tgl_lahir'       => $this->BIRTHDATE,
            'tipe_identitas'  => $this->ID_TYPE,
            'no_identitas'    => $this->ID_NUMBER,
            'no_kk'           => $this->KK_NUMBER,
            'alamat'          => $this->ADDRESS,
            'rt'              => $this->RT,
            'rw'              => $this->RW,
            'provinsi'        => $this->PROVINCE,
            'kota'            => $this->CITY,
            'kecamatan'       => $this->KECAMATAN,
            'kelurahan'       => $this->KELURAHAN,
            'kode_pos'        => $this->ZIP_CODE,
            'nama_ibu'        => $this->MOTHER_NAME,
            'pendidikan'      => $this->EDUCATION,
            'pekerjaan'       => $this->OCCUPATION,
            'status_kawin'    => $this->MARTIAL_STATUS,
            'hp'              => $this->PHONE_PERSONAL,
            'dok_ktp'         => $this->documents ?? null,
        ];
    }
}

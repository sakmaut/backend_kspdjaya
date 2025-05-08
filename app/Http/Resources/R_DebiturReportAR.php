<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DebiturReportAR extends JsonResource
{
    public function toArray(Request $request): array
    {
        $results = [
            'id' => $this->idCust ?? '',
            'cust_code' => $this->CUST_CODE ?? '',
            'pelanggan' => [
                "nama" => $this->NAME ?? '',
                "nama_panggilan" => $this->ALIAS ?? '',
                "jenis_kelamin" => $this->GENDER ?? '',
                "tempat_lahir" => $this->BIRTHPLACE ?? '',
                "tgl_lahir" => date('Y-m-d', strtotime($this->BIRTHDATE)),
                "gol_darah" => $this->BLOOD_TYPE ?? '',
                "ibu_kandung" => $this->MOTHER_NAME ?? '',
                "status_kawin" => $this->MARTIAL_STATUS ?? '',
                "tgl_kawin" => $this->MARTIAL_DATE ?? '',
                "tipe_identitas" => $this->ID_TYPE ?? '',
                "no_identitas" => $this->ID_NUMBER ?? '',
                "no_kk" => $this->KK_NUMBER ?? '',
                "tgl_terbit_identitas" => date('Y-m-d', strtotime($this->ID_ISSUE_DATE)) ?? '',
                "masa_berlaku_identitas" => date('Y-m-d', strtotime($this->ID_VALID_DATE)) ?? '',
                "no_kk" => $this->KK_NUMBER ?? '',
                "warganegara" => $this->CITIZEN ?? ''
            ],
            'alamat_identitas' => [
                "alamat" => $this->ADDRESS ?? '',
                "rt" => $this->RT ?? '',
                "rw" => $this->RW ?? '',
                "provinsi" => $this->PROVINCE ?? '',
                "kota" => $this->CITY ?? '',
                "kelurahan" => $this->KELURAHAN ?? '',
                "kecamatan" => $this->KECAMATAN ?? '',
                "kode_pos" => $this->ZIP_CODE ?? ''
            ],
            'alamat_tagih' => [
                "alamat" => $this->INS_ADDRESS ?? '',
                "rt" => $this->INS_RT ?? '',
                "rw" => $this->INS_RW ?? '',
                "provinsi" => $this->INS_PROVINCE ?? '',
                "kota" => $this->INS_CITY ?? '',
                "kelurahan" => $this->INS_KELURAHAN ?? '',
                "kecamatan" => $this->INS_KECAMATAN ?? '',
                "kode_pos" => $this->INS_ZIP_CODE ?? ''
            ],
            'pekerjaan' => [
                "pekerjaan" => $this->OCCUPATION ?? '',
                "pekerjaan_id" => $this->OCCUPATION_ON_ID ?? '',
                "agama" => $this->RELIGION ?? '',
                "pendidikan" => $this->EDUCATION ?? '',
                "status_rumah" => $this->PROPERTY_STATUS ?? '',
                "telepon_rumah" => $this->PHONE_HOUSE ?? '',
                "telepon_selular" =>  $this->PHONE_PERSONAL ?? '',
                "telepon_kantor" => $this->PHONE_OFFICE ?? '',
                "ekstra1" => $this->EXT_1 ?? '',
                "ekstra2" => $this->EXT_2 ?? ''
            ],
            'kerabat_darurat' => [
                "nama"  => $this->customer_extra->EMERGENCY_NAME ?? '',
                "alamat"  => $this->customer_extra->EMERGENCY_ADDRESS ?? '',
                "rt"  => $this->customer_extra->EMERGENCY_RT ?? '',
                "rw"  => $this->customer_extra->EMERGENCY_RW ?? '',
                "provinsi" => $this->customer_extra->EMERGENCY_PROVINCE ?? '',
                "kota" => $this->customer_extra->EMERGENCY_CITY ?? '',
                "kelurahan" => $this->customer_extra->EMERGENCY_KELURAHAN ?? '',
                "kecamatan" => $this->customer_extra->EMERGENCY_KECAMATAN ?? '',
                "kode_pos" => $this->customer_extra->EMERGENCY_ZIP_CODE ?? '',
                "no_telp" => $this->customer_extra->EMERGENCY_PHONE_HOUSE ?? '',
                "no_hp" => $this->customer_extra->EMERGENCY_PHONE_PERSONAL ?? '',
            ],
            'pasangan' => [
                "nama"  => $this->customer_extra->SPOUSE_NAME ?? '',
                "tempat_lahir" => $this->customer_extra->SPOUSE_BIRTHPLACE ?? '',
                "tgl_lahir" => date('Y-m-d', strtotime($this->customer_extra->SPOUSE_BIRTHDATE)),
                "pekerjaan"  => $this->customer_extra->SPOUSE_OCCUPATION ?? '',
                "ktp"  => $this->customer_extra->SPOUSE_ID_NUMBER ?? '',
                "pendapatan"  => $this->customer_extra->SPOUSE_INCOME ?? '',
                "alamat"  => $this->customer_extra->SPOUSE_ADDRESS ?? '',
                "rt"  => $this->customer_extra->SPOUSE_RT ?? '',
                "rw"  => $this->customer_extra->SPOUSE_RW ?? '',
                "provinsi" => $this->customer_extra->SPOUSE_PROVINCE ?? '',
                "kota" => $this->customer_extra->SPOUSE_CITY ?? '',
                "kelurahan" => $this->customer_extra->SPOUSE_KELURAHAN ?? '',
                "kecamatan" => $this->customer_extra->SPOUSE_KECAMATAN ?? '',
                "kode_pos" => $this->customer_extra->SPOUSE_ZIP_CODE ?? '',
            ],
        ];

        return $results;
    }
}

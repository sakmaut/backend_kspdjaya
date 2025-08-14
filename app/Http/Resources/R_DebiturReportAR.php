<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DebiturReportAR extends JsonResource
{
    public function toArray(Request $request): array
    {
        $results = [
            'id' => $this->customer->ID ?? '',
            'cust_code' => $this->customer->CUST_CODE ?? '',
            'pelanggan' => [
                "nama" => $this->customer->NAME ?? '',
                "nama_mcf" => $this->MCF_ID ?? '',
                "nama_panggilan" => $this->customer->ALIAS ?? '',
                "jenis_kelamin" => $this->customer->GENDER ?? '',
                "tempat_lahir" => $this->customer->BIRTHPLACE ?? '',
                "tgl_lahir" => date('Y-m-d', strtotime($this->customer->BIRTHDATE)),
                "gol_darah" => $this->customer->BLOOD_TYPE ?? '',
                "ibu_kandung" => $this->customer->MOTHER_NAME ?? '',
                "status_kawin" => $this->customer->MARTIAL_STATUS ?? '',
                "tgl_kawin" => $this->customer->MARTIAL_DATE ?? '',
                "tipe_identitas" => $this->customer->ID_TYPE ?? '',
                "no_identitas" => $this->customer->ID_NUMBER ?? '',
                "no_kk" => $this->customer->KK_NUMBER ?? '',
                "tgl_terbit_identitas" => date('Y-m-d', strtotime($this->customer->ID_ISSUE_DATE)) ?? '',
                "masa_berlaku_identitas" => date('Y-m-d', strtotime($this->customer->ID_VALID_DATE)) ?? '',
                "no_kk" => $this->customer->KK_NUMBER ?? '',
                "warganegara" => $this->customer->CITIZEN ?? ''
            ],
            'alamat_identitas' => [
                "alamat" => $this->customer->ADDRESS ?? '',
                "rt" => $this->customer->RT ?? '',
                "rw" => $this->customer->RW ?? '',
                "provinsi" => $this->customer->PROVINCE ?? '',
                "kota" => $this->customer->CITY ?? '',
                "kelurahan" => $this->customer->KELURAHAN ?? '',
                "kecamatan" => $this->customer->KECAMATAN ?? '',
                "kode_pos" => $this->customer->ZIP_CODE ?? ''
            ],
            'alamat_tagih' => [
                "alamat" => $this->customer->INS_ADDRESS ?? '',
                "rt" => $this->customer->INS_RT ?? '',
                "rw" => $this->customer->INS_RW ?? '',
                "provinsi" => $this->customer->INS_PROVINCE ?? '',
                "kota" => $this->customer->INS_CITY ?? '',
                "kelurahan" => $this->customer->INS_KELURAHAN ?? '',
                "kecamatan" => $this->customer->INS_KECAMATAN ?? '',
                "kode_pos" => $this->customer->INS_ZIP_CODE ?? ''
            ],
            'pekerjaan' => [
                "pekerjaan" => $this->customer->OCCUPATION ?? '',
                "pekerjaan_id" => $this->customer->OCCUPATION_ON_ID ?? '',
                "agama" => $this->customer->RELIGION ?? '',
                "pendidikan" => $this->customer->EDUCATION ?? '',
                "status_rumah" => $this->customer->PROPERTY_STATUS ?? '',
                "telepon_rumah" => $this->customer->PHONE_HOUSE ?? '',
                "telepon_selular" =>  $this->customer->PHONE_PERSONAL ?? '',
                "telepon_kantor" => $this->customer->PHONE_OFFICE ?? '',
                "ekstra1" => $this->customer->EXT_1 ?? '',
                "ekstra2" => $this->customer->EXT_2 ?? ''
            ],
            'kerabat_darurat' => [
                "nama"  => $this->customer->customer_extra->EMERGENCY_NAME ?? '',
                "alamat"  => $this->customer->customer_extra->EMERGENCY_ADDRESS ?? '',
                "rt"  => $this->customer->customer_extra->EMERGENCY_RT ?? '',
                "rw"  => $this->customer->customer_extra->EMERGENCY_RW ?? '',
                "provinsi" => $this->customer->customer_extra->EMERGENCY_PROVINCE ?? '',
                "kota" => $this->customer->customer_extra->EMERGENCY_CITY ?? '',
                "kelurahan" => $this->customer->customer_extra->EMERGENCY_KELURAHAN ?? '',
                "kecamatan" => $this->customer->customer_extra->EMERGENCY_KECAMATAN ?? '',
                "kode_pos" => $this->customer->customer_extra->EMERGENCY_ZIP_CODE ?? '',
                "no_telp" => $this->customer->customer_extra->EMERGENCY_PHONE_HOUSE ?? '',
                "no_hp" => $this->customer->customer_extra->EMERGENCY_PHONE_PERSONAL ?? '',
            ],
            'pasangan' => [
                "nama"  => $this->customer->customer_extra->SPOUSE_NAME ?? '',
                "tempat_lahir" => $this->customer->customer_extra->SPOUSE_BIRTHPLACE ?? '',
                "tgl_lahir" => isset($this->customer_extra) && isset($this->customer->customer_extra->SPOUSE_BIRTHDATE) ? date('Y-m-d', strtotime($this->customer->customer_extra->SPOUSE_BIRTHDATE)) : null,
                "pekerjaan"  => $this->customer->customer_extra->SPOUSE_OCCUPATION ?? '',
                "ktp"  => $this->customer->customer_extra->SPOUSE_ID_NUMBER ?? '',
                "pendapatan"  => $this->customer->customer_extra->SPOUSE_INCOME ?? '',
                "alamat"  => $this->customer->customer_extra->SPOUSE_ADDRESS ?? '',
                "rt"  => $this->customer->customer_extra->SPOUSE_RT ?? '',
                "rw"  => $this->customer->customer_extra->SPOUSE_RW ?? '',
                "provinsi" => $this->customer->customer_extra->SPOUSE_PROVINCE ?? '',
                "kota" => $this->customer->customer_extra->SPOUSE_CITY ?? '',
                "kelurahan" => $this->customer->customer_extra->SPOUSE_KELURAHAN ?? '',
                "kecamatan" => $this->customer->customer_extra->SPOUSE_KECAMATAN ?? '',
                "kode_pos" => $this->customer->customer_extra->SPOUSE_ZIP_CODE ?? '',
            ],
        ];

        return $results;
    }
}

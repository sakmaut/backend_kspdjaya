<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailProfile extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'USER_ID' => $request->user()->id,
            'EMPLOYEE_ID' => $request->user()->employee_id,
            'USERNAME' => $request->user()->username,
            'EMAIL' => $request->user()->email,
            'STATUS' => $request->user()->status,
            'ID' => $this->ID,
            'NIK' => $this->NIK,
            'NAMA' => $this->NAMA,
            "AO_CODE" =>$this->AO_CODE,
            "BLOOD_TYPE" => $this->BLOOD_TYPE,
            "GENDER" => $this->GENDER,
            "PENDIDIKAN" => $this->PENDIDIKAN,
            "UNIVERSITAS" => $this->UNIVERSITAS,
            "JURUSAN" => $this->JURUSAN,
            "IPK" => $this->IPK,
            "IBU_KANDUNG" => $this->IBU_KANDUNG,
            "STATUS_KARYAWAN" => $this->STATUS_KARYAWAN,
            "NAMA_PASANGAN" => $this->NAMA_PASANGAN,
            "TANGGUNGAN" => $this->TANGGUNGAN,
            "NO_KTP" => $this->NO_KTP,
            "NAMA_KTP" => $this->NAMA_KTP,
            "ALAMAT_KTP" => $this->ALAMAT_KTP,
            "SECTOR_KTP" => $this->SECTOR_KTP,
            "DISTRICT_KTP" => $this->DISTRICT_KTP,
            "SUB_DISTRICT_KTP" => $this->SUB_DISTRICT_KTP,
            "ALAMAT_TINGGAL" => $this->ALAMAT_TINGGAL,
            "SECTOR_TINGGAL" => $this->SECTOR_TINGGAL,
            "DISTRICT_TINGGAL" => $this->DISTRICT_TINGGAL,
            "SUB_DISTRICT_TINGGAL" => $this->SUB_DISTRICT_TINGGAL,
            "TGL_LAHIR" => $this->TGL_LAHIR,
            "TEMPAT_LAHIR" => $this->TEMPAT_LAHIR,
            "AGAMA" => $this->AGAMA,
            "TELP" => $this->TELP,
            "HP" => $this->HP,
            "NO_REK_CF" => $this->NO_REK_CF,
            "NO_REK_TF" => $this->NO_REK_TF,
            "EMAIL" => $this->EMAIL,
            "NPWP" => $this->NPWP,
            "SUMBER_LOKER" => $this->SUMBER_LOKER,
            "KET_LOKER" => $this->KET_LOKER,
            "INTERVIEW" => $this->INTERVIEW,
            "TGL_KELUAR" => $this->TGL_KELUAR,
            "ALASAN_KELUAR" => $this->ALASAN_KELUAR,
            "CUTI" => $this->CUTI,
            "PHOTO_LOC" => $this->PHOTO_LOC,
            "SPV" => $this->SPV,
            "STATUS_MST" => $this->STATUS_MST,
            "PHOTO_URL" => $request->user()->profile_photo_path
        ];
    }
}

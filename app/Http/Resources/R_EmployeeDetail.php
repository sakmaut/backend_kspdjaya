<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_EmployeeDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = M_Branch::find($this->BRANCH_ID);

        return [
           'employee_id' => $this->ID,
           "nik" => $this->NIK,
           "nama" => $this->NAMA,
           "cabang_id" => $branch->ID,
           "cabang_nama" => $branch->NAME, 
           'jabatan' => $this->JABATAN,
           "blood_type" => $this->BLOOD_TYPE,
           "gender" => $this->GENDER,
           "pendidikan" => $this->PENDIDIKAN,
           "universitas" => $this->UNIVERSITAS,
           "jurusan" => $this->JURUSAN,
           "ipk" => $this->IPK,
           "ibu_kandung" => $this->IBU_KANDUNG,
           "status_karyawan" => $this->STATUS_KARYAWAN,
           "nama_pasangan" => $this->NAMA_PASANGAN,
           "tanggungan" => $this->TANGGUNGAN,
           "no_ktp" => $this->NO_KTP,
           "nama_ktp" => $this->NAMA_KTP,
           'alamat_ktp' => $this->ADDRESS_KTP,
           'rt_ktp' => $this->RT_KTP,
           'rw_ktp' => $this->RW_KTP,
           'provinsi_ktp' => $this->PROVINCE_KTP,
           'kota_ktp' => $this->CITY_KTP,
           'kelurahan_ktp' => $this->KELURAHAN_KTP,
           'kecamatan_ktp' => $this->KECAMATAN_KTP,
           'kode_pos_ktp' => $this->ZIP_CODE_KTP,
           'alamat_tinggal' => $this->ADDRESS,
           'rt' => $this->RT,
           'rw' => $this->RW,
           'provinsi' => $this->PROVINCE,
           'kota' => $this->CITY,
           'kelurahan' => $this->KELURAHAN,
           'kecamatan' => $this->KECAMATAN,
           'kode_pos' => $this->ZIP_CODE,
           "tgl_lahir" => $this->TGL_LAHIR,
           "tempat_lahir" => $this->TEMPAT_LAHIR,
           "agama" => $this->AGAMA,
           "telp" => $this->TELP,
           "hp" => $this->HP,
           "no_rek_tf" => $this->NO_REK_TF,
           "email" => $this->EMAIL,
           "npwp" => $this->NPWP,
           "sumber_loker" => $this->SUMBER_LOKER,
           "ket_loker" => $this->KET_LOKER,
           "interview" => $this->INTERVIEW,
           "tgl_keluar" => $this->TGL_KELUAR,
           "alasan_keluar" => $this->ALASAN_KELUAR,
           "cuti" => $this->CUTI,
           "photo_loc" => "",
           "spv_id" => $this->SPV,
           "status_mst" =>  $this->STATUS_MST,
           "photo_personal" => M_HrEmployeeDocument::attachment($this->ID,'personal'),
           "dokumen" =>[M_HrEmployeeDocument::attachment($this->ID,'file_kary')]
       ];
    }
}

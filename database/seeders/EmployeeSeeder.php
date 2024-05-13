<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('hr_employee')->insert([
            [
                "ID" => "03bf4f5c-4eac-11e9-b250-e0d55e0ad3ad",
                "NIK" => "1709253",
                "NAMA" => "PACQUITO",
                "AO_CODE" => "G7",
                "BLOOD_TYPE" => "B",
                "GENDER" => "Laki-Laki",
                "PENDIDIKAN" => "S1",
                "UNIVERSITAS" => "UNIVERSITAS 17 AGUSTUS 1945 JAKARTA",
                "JURUSAN" => "MANAJEMEN",
                "IPK" => "3.17",
                "IBU_KANDUNG" => "CHUDAEDAH",
                "STATUS_KARYAWAN" => "Menikah",
                "NAMA_PASANGAN" => "DEDE MUNI AH",
                "TANGGUNGAN" => "3",
                "NO_KTP" => "3209062210810001",
                "NAMA_KTP" => "PACQUITO",
                "ALAMAT_KTP" => "JL. A YANI NO. 59",
                "SECTOR_KTP" => "001/005",
                "DISTRICT_KTP" => "SUMUR PECUNG",
                "SUB_DISTRICT_KTP" => "SERANG",
                "ALAMAT_TINGGAL" => "JL. A YANI NO. 59",
                "SECTOR_TINGGAL" => "001/005",
                "DISTRICT_TINGGAL" => "SUMUR PECUNG",
                "SUB_DISTRICT_TINGGAL" => "SERANG",
                "TGL_LAHIR" => "1981-10-22",
                "TEMPAT_LAHIR" => "CIREBON",
                "AGAMA" => "Islam",
                "HP" => "081932335252",
                "NO_REK_CF" => "0012012098",
                "NO_REK_TF" => "704444216000",
                "EMAIL" => "ROKHMAT@bprcahayafajar.co.id",
                "NPWP" => "82.833.139.7-401.000",
                "SUMBER_LOKER" => null,
                "KET_LOKER" => null,
                "INTERVIEW" => null,
                "TGL_KELUAR" => null,
                "ALASAN_KELUAR" => null,
                "CUTI" => "0",
                "PHOTO_LOC" => null,
                "SPV" => null,
                "STATUS_MST" => "Active",
                "CREATED_BY" => "SYSTEM",
                "CREATED_AT" => Carbon::now()
            ],
            // Add more employees here if needed
        ]);
    }
}

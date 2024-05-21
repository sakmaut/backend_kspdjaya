<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    { 
        DB::table('users')->insert([
           [
            'username' => "frontend",
            'employee_id' => '03bf4f5c-4eac-11e9-b250-e0d55e0ad3ad',
            'email' => 'frontend@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('frontend'),
            'remember_token' => Str::random(10),
            'status' => "Active",
           ],
           [
            'username' => "backend",
            'employee_id' => '03bf4f5c-4eac-11e9-b250-e0d55e0ad3ad',
            'email' => 'backend@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('backend'),
            'remember_token' => Str::random(10),
            'status' => "Active",
           ],
        ]);

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
            ]
        ]);

        $menus = [
            [
                "id" => "38912f45-9b99-4779-8463-60e65c3505a9",
                "menu_name" => "laporan kunjungan",
                "route" => "/visit",
                "parent" => "38912f45-9b99-4779-8463-60e65c3505a9",
                "order" => 0,
                "leading" => "ri-road-map-line,ri-road-map-fill",
                "status" => "active",
                "created_at" => Carbon::now(),
                "updated_at" => null
            ],
            [
                "id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a",
                "menu_name" => "input",
                "route" => "/apply-loan",
                "parent" => "38912f45-9b99-4779-8463-60e65c3505a9",
                "order" => 0,
                "leading" => "ri-file-edit-line,ri-file-edit-fill",
                "status" => "active",
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
            ],
            [
                "id"=> "2e7c4719-026a-48af-9662-fe33237da116",
                "menu_name"=> "home",
                "route"=> "/",
                "parent"=> null,
                "order"=> 1,
                "leading"=> "ri-home-smile-2-line,ri-home-smile-2-fill",
                "action"=> null,
                "status"=> "active",
                "ability"=> null,
                "created_by"=> null,
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "39487447-36c3-42c1-a176-cfbaf62e2614",
                "menu_name"=> "karayawan",
                "route"=> "/employees",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-folder-user-line,ri-building-4-fill",
                "action"=> null,
                "status"=> "Active",
                "ability"=> null,
                "created_by"=> "27",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "menu_name"=> "master",
                "route"=> "/master",
                "parent"=> null,
                "order"=> 1,
                "leading"=> "ri-folder-open-line,ri-folder-open-fill",
                "action"=> null,
                "status"=> "active",
                "ability"=> null,
                "created_by"=> "27",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "7ebd9410-7256-4f02-b39e-ffd3e01cb23d",
                "menu_name"=> "Cabang",
                "route"=> "/branch",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-building-4-line,ri-building-4-fill",
                "action"=> null,
                "status"=> "Active",
                "ability"=> null,
                "created_by"=> "27",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f",
                "menu_name"=> "fpk",
                "route"=> "/apply-credit",
                "parent"=> null,
                "order"=> 1,
                "leading"=> "ri-file-add-line,ri-file-add-fill",
                "action"=> null,
                "status"=> "Active",
                "ability"=> null,
                "created_by"=> "2",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "c2051179-5764-48e2-beec-928f4cdbd7fe",
                "menu_name"=> "menu",
                "route"=> "/menu",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-apps-2-line,ri-apps-2-fill",
                "action"=> null,
                "status"=> "Active",
                "ability"=> null,
                "created_by"=> "27",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "e8fdbcb2-b3e3-4d9e-8c24-758736741274",
                "menu_name"=> "pengguna",
                "route"=> "/users",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-user-line,ri-user-fill",
                "action"=> null,
                "status"=> "Active",
                "ability"=> null,
                "created_by"=> "27",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "menu_name"=> "prospek",
                "route"=> "/task",
                "parent"=> null,
                "order"=> 2,
                "leading"=> "ri-file-list-3-line,ri-file-list-3-fill",
                "action"=> null,
                "status"=> "active",
                "ability"=> null,
                "created_by"=> null,
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ]
        ];

        foreach ($menus as $menu) {
            DB::table('master_menu')->insert($menu);
        }
    }
}

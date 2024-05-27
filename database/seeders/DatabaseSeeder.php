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

        DB::table('branch')->insert([
            [
                'ID' => 'c9b93fe8-240f-4a58-991c-f3e42d3cc379',
                'CODE' => 'PST',
                'NAME' => 'Pusat',
                'ADDRESS' => 'Jl Jend Sudirman No.08 Ds.Cipancuh Kec.Haurgeulis, Kab.Indramayu',
                'RT' => '010',
                'RW' => '011',
                'PROVINCE' => 'Jawa Barat',
                'CITY' => 'Indramayu',
                'KELURAHAN' => 'Cipancuh',
                'KECAMATAN' => 'Haurgeulis',
                'ZIP_CODE' =>'45266',
                'LOCATION' => 'Indramayu',
                'PHONE_1' => '+6289651866669',
                'PHONE_2' => '',
                'PHONE_3' => '',
                'DESCR' =>'Kantor Pusat',
                'STATUS' => 'active',
                "CREATE_USER" => "SYSTEM",
                "CREATE_DATE" => Carbon::now()
            ]
        ]);

        DB::table('hr_employee')->insert([
            [
                "ID" => "03bf4f5c-4eac-11e9-b250-e0d55e0ad3ad",
                "NIK" => "1709253",
                "BRANCH_ID" => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
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
                'ADDRESS_KTP' => "TEST",
                'RT_KTP' => "TEST",
                'RW_KTP' => "TEST",
                'PROVINCE_KTP' => "TEST",
                'CITY_KTP' => "TEST",
                'KELURAHAN_KTP' => "TEST",
                'KECAMATAN_KTP' => "TEST",
                'ZIP_CODE_KTP' => "12321",
                'ADDRESS' => "TEST",
                'RT' => "TEST",
                'RW' => "TEST",
                'PROVINCE' => "TEST",
                'CITY' => "TEST",
                'KELURAHAN' => "TEST",
                'KECAMATAN' => "TEST",
                'ZIP_CODE' => "1245",
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
                "id" => "38912f45-9b99-4779-8463-60e65c3505a9",
                "menu_name" => "laporan kunjungan",
                "route" => "/visit",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-road-map-line,ri-road-map-fill",
                "status" => "active",
                "created_at" => Carbon::now(),
                "updated_at" => null
            ],
            [
                "id"=> "39487447-36c3-42c1-a176-cfbaf62e2614",
                "menu_name"=> "karyawan",
                "route"=> "/employees",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-folder-user-line,ri-building-4-fill",
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
                "id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a",
                "menu_name" => "survey",
                "route" => "/survey",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-file-edit-line,ri-file-edit-fill",
                "status" => "active",
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
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
                "id" => "69586e0a-83e2-4ca2-81d3-33cab413b074",
                "menu_name" => "approval",
                "route" => "/approval",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-file-edit-line,ri-file-edit-fill",
                "status" => "active",
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
            ],
            [
                "id"=> "7ebd9410-7256-4f02-b39e-ffd3e01cb23d",
                "menu_name"=> "Cabang",
                "route"=> "/branch",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-building-4-line,ri-building-4-fill",
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
                "id" => "819c88b5-c2e8-48e7-81b6-4bf5c8fadf4f",
                "menu_name" => "survey admin",
                "route" => "/survey-admin",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-file-edit-line,ri-file-edit-fill",
                "status" => "inactive",
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
            ],
            [
                "id"=> "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f",
                "menu_name"=> "FPK",
                "route"=> "/apply-credit",
                "parent"=> 'bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1',
                "order"=> 1,
                "leading"=> "ri-file-add-line,ri-file-add-fill",
                "action"=> null,
                "status"=> "active",
                "ability"=> null,
                "created_by"=> "2",
                "created_at"=> Carbon::now(),
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id"=> "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "menu_name"=> "credit",
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
            ],
            [
                "id"=> "c2051179-5764-48e2-beec-928f4cdbd7fe",
                "menu_name"=> "menu",
                "route"=> "/menu",
                "parent"=> "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "order"=> 1,
                "leading"=> "ri-apps-2-line,ri-apps-2-fill",
                "action"=> null,
                "status"=> "inactive",
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
                "status"=> "inactive",
                "ability"=> null,
                "created_by"=> "27",
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

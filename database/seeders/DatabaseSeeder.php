<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    { 
        DB::table('users')->insert([
            [
                'username' => "frontend",
                'email' => 'frontend@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('frontend'),
                'remember_token' => Str::random(10),
                'status' => "active",
                'fullname' => "frontend",
                'branch_id' => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
                'position' => "ADMIN",
                'gender' => "Laki-laki",
                'mobile_number' => "12345678"
            ],
            [
                'username' => "mcf",
                'email' => 'mcf@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('mcf'),
                'remember_token' => Str::random(10),
                'status' => "active",
                'fullname' => "mcf",
                'branch_id' => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
                'position' => "MCF",
                'gender' => "Laki-laki",
                'mobile_number' => "12345678"
            ],
            [
                'username' => "admin",
                'email' => 'admin@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('admin'),
                'remember_token' => Str::random(10),
                'status' => "active",
                'fullname' => "admin",
                'branch_id' => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
                'position' => "ADMIN",
                'gender' => "Laki-laki",
                'mobile_number' => "12345678"
            ],
            [
                'username' => "kapos",
                'email' => 'kapos@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('kapos'),
                'remember_token' => Str::random(10),
                'status' => "active",
                'fullname' => "kapos",
                'branch_id' => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
                'position' => "KAPOS",
                'gender' => "Laki-laki",
                'mobile_number' => "12345678"
            ],
            [
                'username' => "ho",
                'email' => 'ho@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('ho'),
                'remember_token' => Str::random(10),
                'status' => "active",
                'fullname' => "ho",
                'branch_id' => "c9b93fe8-240f-4a58-991c-f3e42d3cc379",
                'position' => "HO",
                'gender' => "Laki-laki",
                'mobile_number' => "12345678"
            ]
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

        DB::table('master_menu')->insert([
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
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ],
            [
                "id" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "menu_name" => "credit",
                "route" => "/task",
                "parent" => null,
                "order" => 2,
                "leading" => "ri-file-list-3-line,ri-file-list-3-fill",
                "action" => null,
                "status" => "active",
                "ability" => null,
                "created_by" => null,
                "updated_by" => null,
                "updated_at" => null,
                "deleted_by" => null,
                "deleted_at" => null
            ],
            [
                "id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a",
                "menu_name" => "survey",
                "route" => "/survey",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-file-edit-line,ri-file-edit-fill",
                "action" => null,
                "status" => "active",
                "ability" => null,
                "created_by" => null,
                "updated_by" => null,
                "updated_at" => null,
                "deleted_by" => null,
                "deleted_at" => null
            ],
            [
                "id" => "69586e0a-83e2-4ca2-81d3-33cab413b074",
                "menu_name" => "approval",
                "route" => "/approval",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-checkbox-line,ri-checkbox-fill",
                "action" => null,
                "status" => "active",
                "ability" => null,
                "created_by" => null,
                "updated_by" => null,
                "updated_at" => null,
                "deleted_by" => null,
                "deleted_at" => null
            ],
            [
                "id" => "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f",
                "menu_name" => "Fpk",
                "route" => "/apply-credit",
                "parent" => "bf8e35eb-9f2a-4a40-96ec-f2e0158d12e1",
                "order" => 0,
                "leading" => "ri-file-add-line,ri-file-add-fill",
                "action" => null,
                "status" => "active",
                "ability" => null,
                "created_by" => "SYSTEM",
                "updated_by" => null,
                "updated_at" => null,
                "deleted_by" => null,
                "deleted_at" => null
            ],
            [
                "id" => "69586e0a-83e2-4ca2-81d3-33cab413b073",
                "menu_name" => "master",
                "route" => "/master",
                "parent" => null,
                "order" => 1,
                "leading" => "ri-folder-open-line,ri-folder-open-fill",
                "action" => null,
                "status" => "active",
                "ability" => null,
                "created_by" => "SYSTEM",
                "updated_by" => null,
                "updated_at" => null,
                "deleted_by" => null,
                "deleted_at" => null
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
                "created_by"=> "SYSTEM",
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
                "status"=> "active",
                "ability"=> null,
                "created_by"=> "SYSTEM",
                "updated_by"=> null,
                "updated_at"=> null,
                "deleted_by"=> null,
                "deleted_at"=> null
            ]
        ]);

        DB::table('master_users_access_menu')->insert([
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "69586e0a-83e2-4ca2-81d3-33cab413b074",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "7ebd9410-7256-4f02-b39e-ffd3e01cb23d",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "e8fdbcb2-b3e3-4d9e-8c24-758736741274",
                "users_id" => "1",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            //Admin
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116",
                "users_id" => "3",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f",
                "users_id" => "3",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "7ebd9410-7256-4f02-b39e-ffd3e01cb23d",
                "users_id" => "3",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "e8fdbcb2-b3e3-4d9e-8c24-758736741274",
                "users_id" => "3",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            //End Admin

            //MCF
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116",
                "users_id" => "2",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a",
                "users_id" => "2",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            //End MCF
            //Kapos
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116",
                "users_id" => "4",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "master_menu_id" => "69586e0a-83e2-4ca2-81d3-33cab413b074",
                "users_id" => "4",
                "created_by" => "SYSTEM",
                "created_at" => Carbon::now()
            ],
            //End Kapos
        ]);

        DB::table('jabatan_access_menu')->insert([
            //ADMIN
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "ADMIN",
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116"
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "ADMIN",
                "master_menu_id" => "819c88b5-c2e8-48e7-81b6-4bf5c8fadf6f"
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "ADMIN",
                "master_menu_id" => "7ebd9410-7256-4f02-b39e-ffd3e01cb23d"
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "ADMIN",
                "master_menu_id" => "e8fdbcb2-b3e3-4d9e-8c24-758736741274"
            ],
            //END ADMIN
            //Kapos
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "KAPOS",
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116"
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "KAPOS",
                "master_menu_id" => "69586e0a-83e2-4ca2-81d3-33cab413b074"
            ],
            //END Kapos
            //mcf
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "MCF",
                "master_menu_id" => "2e7c4719-026a-48af-9662-fe33237da116"
            ],
            [
                "id" => Uuid::uuid7()->toString(),
                "jabatan" => "MCF",
                "master_menu_id" => "43ab0741-338f-4e53-adf6-0fbc2c832b8a"
            ],
            //END mcf
        ]);
    }
}

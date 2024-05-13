<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        \App\Models\User::factory(2)->create();
        
        DB::table('users')->insert([
            'username' => "frontend",
            'employee_id' => '03bf4f5c-4eac-11e9-b250-e0d55e0ad3ad',
            'email' => 'frontend@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('frontend'),
            'remember_token' => Str::random(10),
            'status' => "Active",
        ]);
    }
}

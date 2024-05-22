<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_employee', function (Blueprint $table) {
            $table->string('ID',100)->primary();
            $table->string('NIK')->unique();
            $table->string('NAMA')->nullable();
            $table->string('BRANCH_ID',100)->nullable();
            $table->string('AO_CODE')->nullable();
            $table->string('BLOOD_TYPE')->nullable();
            $table->string('GENDER')->nullable();
            $table->string('PENDIDIKAN')->nullable();
            $table->string('UNIVERSITAS')->nullable();
            $table->string('JURUSAN')->nullable();
            $table->string('IPK')->nullable();
            $table->string('IBU_KANDUNG')->nullable();
            $table->string('STATUS_KARYAWAN')->nullable();
            $table->string('NAMA_PASANGAN')->nullable();
            $table->string('TANGGUNGAN')->nullable();
            $table->string('NO_KTP')->nullable();
            $table->string('NAMA_KTP')->nullable();
            $table->string('ADDRESS_KTP')->nullable(); 
            $table->string('RT_KTP')->nullable();
            $table->string('RW_KTP')->nullable();
            $table->string('PROVINCE_KTP')->nullable();
            $table->string('CITY_KTP')->nullable();
            $table->string('KELURAHAN_KTP')->nullable();
            $table->string('KECAMATAN_KTP')->nullable();
            $table->string('ZIP_CODE_KTP')->nullable();
            $table->string('ADDRESS')->nullable(); 
            $table->string('RT')->nullable();
            $table->string('RW')->nullable();
            $table->string('PROVINCE')->nullable();
            $table->string('CITY')->nullable();
            $table->string('KELURAHAN')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('ZIP_CODE')->nullable();
            $table->date('TGL_LAHIR')->nullable();
            $table->string('TEMPAT_LAHIR')->nullable();
            $table->string('AGAMA')->nullable();
            $table->string('TELP')->nullable();
            $table->string('HP')->nullable();
            $table->string('NO_REK_CF')->nullable();
            $table->string('NO_REK_TF')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('NPWP')->nullable();
            $table->string('SUMBER_LOKER')->nullable();
            $table->string('KET_LOKER')->nullable();
            $table->string('INTERVIEW')->nullable();
            $table->string('TGL_KELUAR')->nullable();
            $table->string('ALASAN_KELUAR')->nullable();
            $table->string('CUTI')->nullable();
            $table->string('PHOTO_LOC')->nullable();
            $table->string('SPV')->nullable();
            $table->string('STATUS_MST')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->nullable();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('UPDATED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_employee');
    }
};

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
        Schema::table('hr_employee', function (Blueprint $table) {
            $table->string('ADDRESS_KTP')->nullable()->after('NAMA_KTP'); 
            $table->string('RT_KTP')->nullable()->after('ADDRESS_KTP');
            $table->string('RW_KTP')->nullable()->after('RT_KTP');
            $table->string('PROVINCE_KTP')->nullable()->after('RW_KTP');
            $table->string('CITY_KTP')->nullable()->after('PROVINCE_KTP');
            $table->string('KELURAHAN_KTP')->nullable()->after('CITY_KTP');
            $table->string('KECAMATAN_KTP')->nullable()->after('KELURAHAN_KTP');
            $table->string('ZIP_CODE_KTP')->nullable()->after('KECAMATAN_KTP');
            $table->string('ADDRESS')->nullable()->after('ZIP_CODE_KTP'); 
            $table->string('RT')->nullable()->after('ADDRESS');
            $table->string('RW')->nullable()->after('RT');
            $table->string('PROVINCE')->nullable()->after('RW');
            $table->string('CITY')->nullable()->after('PROVINCE');
            $table->string('KELURAHAN')->nullable()->after('CITY');
            $table->string('KECAMATAN')->nullable()->after('KELURAHAN');
            $table->string('ZIP_CODE')->nullable()->after('KECAMATAN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

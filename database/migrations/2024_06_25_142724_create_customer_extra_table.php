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
        Schema::create('customer_extra', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CUST_CODE', 45)->nullable();
            $table->string('OTHER_OCCUPATION_1', 100)->nullable();
            $table->string('OTHER_OCCUPATION_2', 100)->nullable();
            $table->string('SPOUSE_NAME', 100)->nullable();
            $table->string('SPOUSE_BIRTHPLACE', 45)->nullable();
            $table->date('SPOUSE_BIRTHDATE')->nullable();
            $table->string('SPOUSE_ID_NUMBER', 45)->nullable();
            $table->decimal('SPOUSE_INCOME', 15, 2)->nullable();
            $table->string('SPOUSE_ADDRESS', 200)->nullable();
            $table->string('SPOUSE_RT', 45)->nullable();
            $table->string('SPOUSE_RW', 45)->nullable();
            $table->string('SPOUSE_PROVINCE', 45)->nullable();
            $table->string('SPOUSE_CITY', 45)->nullable();
            $table->string('SPOUSE_KELURAHAN', 45)->nullable();
            $table->string('SPOUSE_KECAMATAN', 45)->nullable();
            $table->string('SPOUSE_ZIP_CODE', 45)->nullable();
            $table->string('INS_ADDRESS', 200)->nullable();
            $table->string('INS_RT', 45)->nullable();
            $table->string('INS_RW', 45)->nullable();
            $table->string('INS_PROVINCE', 45)->nullable();
            $table->string('INS_CITY', 45)->nullable();
            $table->string('INS_KELURAHAN', 45)->nullable();
            $table->string('INS_KECAMATAN', 45)->nullable();
            $table->string('INS_ZIP_CODE', 45)->nullable();
            $table->string('EMERGENCY_NAME', 45)->nullable();
            $table->string('EMERGENCY_ADDRESS', 200)->nullable();
            $table->string('EMERGENCY_RT', 45)->nullable();
            $table->string('EMERGENCY_RW', 45)->nullable();
            $table->string('EMERGENCY_PROVINCE', 45)->nullable();
            $table->string('EMERGENCYL_CITY', 45)->nullable();
            $table->string('EMERGENCY_KELURAHAN', 45)->nullable();
            $table->string('EMERGENCYL_KECAMATAN', 45)->nullable();
            $table->string('EMERGENCY_ZIP_CODE', 45)->nullable();
            $table->string('EMERGENCY_PHONE_HOUSE', 45)->nullable();
            $table->string('EMERGENCY_PHONE_PERSONAL', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_extra');
    }
};

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
        Schema::create('cr_personal', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CUST_CODE', 45)->nullable();
            $table->string('NAME', 100)->nullable();
            $table->string('ALIAS', 100)->nullable();
            $table->string('GENDER', 45)->nullable();
            $table->string('BIRTHPLACE', 45)->nullable();
            $table->date('BIRTHDATE')->nullable();
            $table->string('MARTIAL_STATUS', 45)->nullable();
            $table->date('MARTIAL_DATE')->nullable();
            $table->string('ID_TYPE', 45)->nullable();
            $table->string('ID_NUMBER', 45)->nullable();
            $table->date('ID_ISSUE_DATE')->nullable();
            $table->date('ID_VALID_DATE')->nullable();
            $table->string('ADDRESS', 200)->nullable();
            $table->string('RT', 45)->nullable();
            $table->string('RW', 45)->nullable();
            $table->string('PROVINCE', 45)->nullable();
            $table->string('CITY', 45)->nullable();
            $table->string('KELURAHAN', 45)->nullable();
            $table->string('KECAMATAN', 45)->nullable();
            $table->string('ZIP_CODE', 45)->nullable();
            $table->string('KK', 45)->nullable();
            $table->string('CITIZEN', 45)->nullable();
            $table->string('INS_ADDRESS', 200)->nullable();
            $table->string('INS_RT', 45)->nullable();
            $table->string('INS_RW', 45)->nullable();
            $table->string('INS_PROVINCE', 45)->nullable();
            $table->string('INS_CITY', 45)->nullable();
            $table->string('INS_KELURAHAN', 45)->nullable();
            $table->string('INS_KECAMATAN', 45)->nullable();
            $table->string('INS_ZIP_CODE', 45)->nullable();
            $table->string('OCCUPATION', 45)->nullable();
            $table->string('OCCUPATION_ON_ID', 45)->nullable();
            $table->string('RELIGION', 45)->nullable();
            $table->string('EDUCATION', 45)->nullable();
            $table->string('PROPERTY_STATUS', 45)->nullable();
            $table->string('PHONE_HOUSE', 45)->nullable();
            $table->string('PHONE_PERSONAL', 45)->nullable();
            $table->string('PHONE_OFFICE', 45)->nullable();
            $table->string('EXT_1', 45)->nullable();
            $table->string('EXT_2', 45)->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY', 45)->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY', 45)->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_personal');
    }
};

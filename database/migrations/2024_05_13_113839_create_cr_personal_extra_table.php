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
        Schema::create('cr_personal_extra', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('APPLICATION_ID', 100)->nullable();
            $table->string('BI_NAME', 45)->nullable();
            $table->string('EMAIL', 45)->nullable();
            $table->string('INFO', 45)->nullable();
            $table->string('OTHER_OCCUPATION_1', 100)->nullable();
            $table->string('OTHER_OCCUPATION_2', 100)->nullable();
            $table->string('OTHER_OCCUPATION_3', 100)->nullable();
            $table->string('OTHER_OCCUPATION_4', 100)->nullable();
            $table->string('MAIL_ADDRESS', 200)->nullable();
            $table->string('MAIL_RT', 45)->nullable();
            $table->string('MAIL_RW', 45)->nullable();
            $table->string('MAIL_PROVINCE', 45)->nullable();
            $table->string('MAIL_CITY', 45)->nullable();
            $table->string('MAIL_KELURAHAN', 45)->nullable();
            $table->string('MAIL_KECAMATAN', 45)->nullable();
            $table->string('MAIL_ZIP_CODE', 45)->nullable();
            $table->string('EMERGENCY_NAME', 45)->nullable();
            $table->string('EMERGENCY_ADDRESS', 200)->nullable();
            $table->string('EMERGENCY_RT', 45)->nullable();
            $table->string('EMERGENCY_RW', 45)->nullable();
            $table->string('EMERGENCY_PROVINCE', 45)->nullable();
            $table->string('EMERGENCY_CITY', 45)->nullable();
            $table->string('EMERGENCY_KELURAHAN', 45)->nullable();
            $table->string('EMERGENCY_KECAMATAN', 45)->nullable();
            $table->string('EMERGENCY_ZIP_CODE', 45)->nullable();
            $table->string('EMERGENCY_PHONE_HOUSE', 45)->nullable();
            $table->string('EMERGENCY_PHONE_PERSONAL', 45)->nullable();

            $table->foreign('APPLICATION_ID')->references('ID')->on('cr_application')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_personal_extra');
    }
};

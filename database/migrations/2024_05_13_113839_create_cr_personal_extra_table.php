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
            $table->string('ID')->primary();
            $table->string('APPLICATION_ID')->nullable();
            $table->string('BI_NAME')->nullable();
            $table->string('EMAIL')->nullable();
            $table->string('INFO')->nullable();
            $table->string('OTHER_OCCUPATION_1')->nullable();
            $table->string('OTHER_OCCUPATION_2')->nullable();
            $table->string('OTHER_OCCUPATION_3')->nullable();
            $table->string('OTHER_OCCUPATION_4')->nullable();
            $table->string('MAIL_ADDRESS')->nullable();
            $table->string('MAIL_RT')->nullable();
            $table->string('MAIL_RW')->nullable();
            $table->string('MAIL_PROVINCE')->nullable();
            $table->string('MAIL_CITY')->nullable();
            $table->string('MAIL_KELURAHAN')->nullable();
            $table->string('MAIL_KECAMATAN')->nullable();
            $table->string('MAIL_ZIP_CODE')->nullable();
            $table->string('EMERGENCY_NAME')->nullable();
            $table->string('EMERGENCY_ADDRESS')->nullable();
            $table->string('EMERGENCY_RT')->nullable();
            $table->string('EMERGENCY_RW')->nullable();
            $table->string('EMERGENCY_PROVINCE')->nullable();
            $table->string('EMERGENCY_CITY')->nullable();
            $table->string('EMERGENCY_KELURAHAN')->nullable();
            $table->string('EMERGENCY_KECAMATAN')->nullable();
            $table->string('EMERGENCY_ZIP_CODE')->nullable();
            $table->string('EMERGENCY_PHONE_HOUSE')->nullable();
            $table->string('EMERGENCY_PHONE_PERSONAL')->nullable();

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

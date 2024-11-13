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
            $table->string('ID')->primary();
            $table->string('APPLICATION_ID')->nullable();
            $table->string('CUST_CODE')->nullable();
            $table->string('NAME')->nullable();
            $table->string('ALIAS')->nullable();
            $table->string('GENDER')->nullable();
            $table->string('BIRTHPLACE')->nullable();
            $table->date('BIRTHDATE')->nullable()->default(null);
            $table->string('BLOOD_TYPE')->nullable();
            $table->string('MARTIAL_STATUS')->nullable();
            $table->date('MARTIAL_DATE')->nullable()->default(null);
            $table->string('ID_TYPE')->nullable();
            $table->string('ID_NUMBER')->nullable();
            $table->date('ID_ISSUE_DATE')->nullable()->default(null);
            $table->date('ID_VALID_DATE')->nullable()->default(null);
            $table->string('ADDRESS', 200)->nullable();
            $table->string('RT')->nullable();
            $table->string('RW')->nullable();
            $table->string('PROVINCE')->nullable();
            $table->string('CITY')->nullable();
            $table->string('KELURAHAN')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('ZIP_CODE')->nullable();
            $table->string('KK')->nullable();
            $table->string('CITIZEN')->nullable();
            $table->string('INS_ADDRESS', 200)->nullable();
            $table->string('INS_RT')->nullable();
            $table->string('INS_RW')->nullable();
            $table->string('INS_PROVINCE')->nullable();
            $table->string('INS_CITY')->nullable();
            $table->string('INS_KELURAHAN')->nullable();
            $table->string('INS_KECAMATAN')->nullable();
            $table->string('INS_ZIP_CODE')->nullable();
            $table->string('OCCUPATION')->nullable();
            $table->string('OCCUPATION_ON_ID')->nullable();
            $table->string('RELIGION')->nullable();
            $table->string('EDUCATION')->nullable();
            $table->string('PROPERTY_STATUS')->nullable();
            $table->string('PHONE_HOUSE')->nullable();
            $table->string('PHONE_PERSONAL')->nullable();
            $table->string('PHONE_OFFICE')->nullable();
            $table->string('EXT_1')->nullable();
            $table->string('EXT_2')->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY')->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
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

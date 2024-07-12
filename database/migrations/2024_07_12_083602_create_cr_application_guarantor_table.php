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
        Schema::create('cr_application_guarantor', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('APPLICATION_ID')->nullable();
            $table->string('NAME')->nullable();
            $table->string('GENDER')->nullable();
            $table->string('BIRTHPLACE')->nullable();
            $table->date('BIRTHDATE')->nullable();
            $table->string('ADDRESS')->nullable();
            $table->string('IDENTITY_TYPE')->nullable();
            $table->string('NUMBER_IDENTITY')->nullable();
            $table->string('OCCUPATION')->nullable();
            $table->string('WORK_PERIOD')->nullable();
            $table->string('STATUS_WITH_DEBITUR')->nullable();
            $table->string('MOBILE_NUMBER')->nullable();
            $table->string('INCOME')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_application_guarantor');
    }
};

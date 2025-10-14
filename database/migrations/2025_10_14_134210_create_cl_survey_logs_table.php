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
        Schema::create('cl_survey_logs', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('REFERENCE_ID')->nullable(false);
            $table->text('DESCRIPTION')->nullable();
            $table->date('CONFIRM_DATE')->nullable();
            $table->string('PATH')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_survey_logs');
    }
};

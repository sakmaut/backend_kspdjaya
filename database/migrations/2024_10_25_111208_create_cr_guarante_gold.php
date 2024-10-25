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
        Schema::create('cr_guarante_gold', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CR_SURVEY_ID')->nullable(false);
            $table->string('STATUS_JAMINAN')->nullable();
            $table->string('KODE_EMAS')->nullable();
            $table->string('BERAT')->nullable();
            $table->string('UNIT')->nullable();
            $table->string('ATAS_NAMA')->nullable();
            $table->decimal('NOMINAL', 20, 2)->nullable();
            $table->string('COLLATERAL_FLAG')->nullable();
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
        Schema::dropIfExists('cr_guarante_gold');
    }
};

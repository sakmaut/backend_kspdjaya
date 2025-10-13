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
        Schema::create('cl_deploy', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('USER_ID')->nullable(false);
            $table->string('LOAN_NUMBER')->nullable(false);
            $table->date('TGL_JTH_TEMPO')->nullable();
            $table->string('NAMA_CUST')->nullable();
            $table->string('CYCLE_AWAL')->nullable();
            $table->string('N_BOT')->nullable();
            $table->text('ALAMAT')->nullable();
            $table->text('DESA')->nullable();
            $table->text('KEC')->nullable();
            $table->date('TGL_EXP')->nullable();
            $table->date('TGL_KUNJUNGAN')->nullable();
            $table->text('KETERANGAN')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
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
        Schema::dropIfExists('cl_deploy');
    }
};

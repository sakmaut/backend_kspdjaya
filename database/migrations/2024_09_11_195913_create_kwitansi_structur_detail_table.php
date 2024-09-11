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
        Schema::create('kwitansi_structur_detail', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('no_invoice')->nullable();
            $table->string('key')->nullable();
            $table->string('angsuran_ke')->nullable();
            $table->string('loan_number')->nullable();
            $table->string('tgl_angsuran')->nullable();
            $table->string('principal')->nullable();
            $table->string('interest')->nullable();
            $table->string('installment')->nullable();
            $table->string('principal_remains')->nullable();
            $table->string('payment')->nullable();
            $table->string('bayar_angsuran')->nullable();
            $table->string('bayar_denda')->nullable();
            $table->string('total_bayar')->nullable();
            $table->string('flag')->nullable();
            $table->string('denda')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kwitansi_structur_detail');
    }
};

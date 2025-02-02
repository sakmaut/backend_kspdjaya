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
        Schema::create('kwitansi_detail_pelunasan', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('no_invoice')->nullable();
            $table->string('loan_number')->nullable();
            $table->string('angsuran_ke')->nullable();
            $table->string('tgl_angsuran')->nullable();
            $table->string('installment')->nullable();
            $table->decimal('bayar_pokok',25,2)->nullable()->default(0);
            $table->decimal('bayar_bunga', 25, 2)->nullable()->default(0);
            $table->decimal('bayar_denda', 25, 2)->nullable()->default(0);
            $table->decimal('diskon_pokok', 25, 2)->nullable()->default(0);
            $table->decimal('diskon_bunga', 25, 2)->nullable()->default(0);
            $table->decimal('diskon_denda', 25, 2)->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kwitansi_detail_pelunasan');
    }
};

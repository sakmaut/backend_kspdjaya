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
        Schema::create('kwitansi', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('NO_TRANSAKSI')->nullable();
            $table->string('TGL_TRANSAKSI')->nullable();
            $table->string('CUST_CODE')->nullable();
            $table->string('NAMA')->nullable();
            $table->string('ALAMAT')->nullable();
            $table->string('RT')->nullable();
            $table->string('RW')->nullable();
            $table->string('PROVINSI')->nullable();
            $table->string('KOTA')->nullable();
            $table->string('KELURAHAN')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('METODE_PEMBAYARAN')->nullable();
            $table->string('PEMBULATAN')->nullable();
            $table->string('KEMBALIAN')->nullable();
            $table->string('JUMLAH_UANG')->nullable();
            $table->string('TOTAL_BAYAR')->nullable();
            $table->string('NAMA_BANK')->nullable();
            $table->string('NO_REKENING')->nullable();
            $table->string('BUKTI_TRANSFER')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kwitansi');
    }
};

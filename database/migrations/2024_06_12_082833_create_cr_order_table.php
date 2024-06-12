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
        Schema::create('cr_order', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('APPLICATION_ID', 100)->nullable(false);
            $table->date('ORDER_TANGGAL')->nullable();
            $table->string('ORDER_STATUS')->nullable();
            $table->string('ORDER_TIPE')->nullable();
            $table->string('UNIT_BISNIS')->nullable();
            $table->string('CUST_SERVICE')->nullable();
            $table->string('REF_PELANGGAN')->nullable();
            $table->string('PROG_MARKETING')->nullable();
            $table->string('CARA_BAYAR')->nullable();
            $table->string('KODE_BARANG')->nullable();
            $table->string('ID_TIPE')->nullable();
            $table->string('TAHUN')->nullable();
            $table->string('HARGA_PASAR')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_order');
    }
};

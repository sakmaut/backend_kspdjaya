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
        Schema::table('kwitansi', function (Blueprint $table) {
            $table->double('BAYAR_POKOK', 25, 2)->default(0)->after('DISKON_PINALTY_PELUNASAN');
            $table->double('DISKON_POKOK', 25, 2)->default(0)->after('BAYAR_POKOK');
            $table->double('BAYAR_BUNGA', 25, 2)->default(0)->after('DISKON_POKOK');
            $table->double('DISKON_BUNGA', 25, 2)->default(0)->after('BAYAR_BUNGA');
            $table->double('BAYAR_DENDA', 25, 2)->default(0)->after('DISKON_BUNGA');
            $table->double('DISKON_DENDA', 25, 2)->default(0)->after('BAYAR_DENDA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

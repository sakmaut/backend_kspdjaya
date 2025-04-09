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
        Schema::table('kwitansi_structur_detail', function (Blueprint $table) {
            $table->decimal('principal', 25, 2)->change();
            $table->decimal('interest', 25, 2)->change();
            $table->decimal('installment', 25, 2)->change();
            $table->decimal('principal_remains', 25, 2)->change();
            $table->decimal('bayar_angsuran', 25, 2)->change();
            $table->decimal('bayar_denda', 25, 2)->change();
            $table->decimal('total_bayar', 25, 2)->change();
            $table->decimal('denda', 25, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decimal', function (Blueprint $table) {
            //
        });
    }
};

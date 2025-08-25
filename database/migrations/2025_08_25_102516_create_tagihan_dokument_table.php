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
        Schema::create('tagihan_dokument', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('TAGIHAN_ID')->nullable(false);
            $table->integer('ORDER')->default(0);
            $table->string('PATH')->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan_dokument');
    }
};

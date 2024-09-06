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
        Schema::create('bpkb_detail', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('BPKB_TRANSACTION_ID', 100)->nullable();
            $table->string('COLLATERAL_ID', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpkb_detail');
    }
};

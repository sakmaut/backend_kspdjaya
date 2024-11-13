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
        Schema::create('payment_detail', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('PAYMENT_ID')->nullable();
            $table->string('ACC_KEYS')->nullable();
            $table->double('ORIGINAL_AMOUNT')->nullable();
            $table->double('OS_AMOUNT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_detail');
    }
};

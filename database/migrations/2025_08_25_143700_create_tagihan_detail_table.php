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
        Schema::create('tagihan_detail', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('TAGIHAN_ID')->nullable(false);
            $table->integer('INSTALLMENT_COUNT')->nullable();
            $table->date('PAYMENT_DATE')->nullable();
            $table->decimal('PRINCIPAL', 15, 2)->nullable();
            $table->decimal('INTEREST', 15, 2)->nullable();
            $table->decimal('INSTALLMENT', 15, 2)->nullable();
            $table->decimal('PRINCIPAL_REMAINS', 15, 2)->nullable();
            $table->decimal('PAYMENT_VALUE_PRINCIPAL', 15, 2)->nullable();
            $table->decimal('PAYMENT_VALUE_INTEREST', 15, 2)->nullable();
            $table->decimal('DISCOUNT_PRINCIPAL', 15, 2)->nullable();
            $table->decimal('DISCOUNT_INTEREST', 15, 2)->nullable();
            $table->decimal('INSUFFICIENT_PAYMENT', 15, 2)->nullable();
            $table->decimal('PAYMENT_VALUE', 15, 2)->nullable();
            $table->string('PAID_FLAG', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan_detail');
    }
};

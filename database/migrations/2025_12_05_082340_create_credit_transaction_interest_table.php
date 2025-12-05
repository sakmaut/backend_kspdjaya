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
        Schema::create('credit_transaction_interest', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('LOAN_NUMBER', 25)->nullable();
            $table->string('ACC_KEYS', 50)->nullable();
            $table->integer('PRINCIPAL')->default(0);
            $table->integer('INTEREST')->default(0);
            $table->integer('AMOUNT')->default(0);
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transaction_interest');
    }
};

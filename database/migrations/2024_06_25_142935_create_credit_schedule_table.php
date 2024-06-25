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
        Schema::create('credit_schedule', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('LOAN_NUMBER', 45)->nullable();
            $table->date('PAYMENT_DATE')->nullable();
            $table->decimal('PRINCIPAL', 20, 2)->nullable();
            $table->decimal('INTEREST', 20, 2)->nullable();
            $table->decimal('INSTALLMENT', 20, 2)->nullable();
            $table->decimal('PRINCIPAL_REMAINS', 20, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_schedule');
    }
};

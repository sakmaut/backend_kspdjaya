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
        Schema::create('credit_schedule_before', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('NO_INVOICE')->nullable();
            $table->string('LOAN_NUMBER')->nullable();
            $table->integer('INSTALLMENT_COUNT')->nullable();
            $table->date('PAYMENT_DATE')->nullable();
            $table->decimal('PRINCIPAL', 15, 2)->default(0);
            $table->decimal('INTEREST', 15, 2)->default(0);
            $table->decimal('INSTALLMENT', 15, 2)->default(0);
            $table->decimal('PRINCIPAL_REMAINS', 15, 2)->default(0);
            $table->decimal('PAYMENT_VALUE_PRINCIPAL', 15, 2)->default(0);
            $table->decimal('PAYMENT_VALUE_INTEREST', 15, 2)->default(0);
            $table->decimal('DISCOUNT_PRINCIPAL', 15, 2)->default(0);
            $table->decimal('DISCOUNT_INTEREST', 15, 2)->default(0);
            $table->decimal('INSUFFICIENT_PAYMENT', 15, 2)->default(0);
            $table->decimal('PAYMENT_VALUE', 15, 2)->default(0);
            $table->string('PAID_FLAG')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_schedule_before');
    }
};

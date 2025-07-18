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
        Schema::create('credit_before', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('NO_INVOICE')->nullable();
            $table->string('LOAN_NUMBER')->nullable();
            $table->decimal('PCPL_ORI', 15, 2)->default(0);
            $table->decimal('INTRST_ORI', 15, 2)->default(0);
            $table->decimal('PAID_PRINCIPAL', 15, 2)->default(0);
            $table->decimal('PAID_INTEREST', 15, 2)->default(0);
            $table->decimal('PAID_PENALTY', 15, 2)->default(0);
            $table->decimal('DISCOUNT_PRINCIPAL', 15, 2)->default(0);
            $table->decimal('DISCOUNT_INTEREST', 15, 2)->default(0);
            $table->decimal('DISCOUNT_PENALTY', 15, 2)->default(0);
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_before');
    }
};

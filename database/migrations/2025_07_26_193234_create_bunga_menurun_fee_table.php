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
        Schema::create('bunga_menurun_fee', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->decimal('LOAN_AMOUNT', 15, 2)->default(0);
            $table->integer('INTEREST_PERCENTAGE')->default(0);
            $table->decimal('INSTALLMENT', 15, 2)->default(0);
            $table->decimal('ADMIN_FEE', 15, 2)->default(0);
            $table->decimal('INTEREST_FEE', 15, 2)->default(0);
            $table->decimal('PROCCESS_FEE', 15, 2)->default(0);
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('UPDATED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bunga_menurun_fee');
    }
};

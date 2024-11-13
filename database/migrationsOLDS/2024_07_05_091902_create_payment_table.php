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
        Schema::create('payment', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('STTS_RCRD', 10)->nullable();
            $table->string('BRANCH', 20)->nullable();
            $table->string('LOAN_NUM', 45)->nullable();
            $table->dateTime('VALUE_DATE')->nullable();
            $table->dateTime('ENTRY_DATE')->nullable();
            $table->string('TITLE', 100)->nullable();
            $table->decimal('ORIGINAL_AMOUNT', 20, 2)->nullable();
            $table->decimal('OS_AMOUNT', 20, 2)->nullable();
            $table->integer('CALC_DAYS')->nullable();
            $table->string('SETTLE_ACCOUNT', 45)->nullable();
            $table->dateTime('START_DATE')->nullable();
            $table->dateTime('END_DATE')->nullable();
            $table->string('USER_ID', 45)->nullable();
            $table->dateTime('LAST_JOB_DATE')->nullable();
            $table->string('AUTH_BY', 100)->nullable();
            $table->dateTime('AUTH_DATE')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};

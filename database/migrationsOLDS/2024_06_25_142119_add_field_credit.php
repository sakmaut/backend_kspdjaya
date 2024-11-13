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
        Schema::table('credit', function (Blueprint $table) {
            $table->string('STATUS_REC', 45)->nullable();
            $table->string('BRANCH', 45)->nullable();
            $table->string('CUST_CODE', 45)->nullable();
            $table->string('ORDER_NUMBER', 45)->nullable();
            $table->string('COLLECTIBILITY', 45)->nullable();
            $table->string('MCF_ID', 45)->nullable();
            $table->dateTime('ENTRY_DATE')->nullable();
            $table->dateTime('END_DATE')->nullable();
            $table->dateTime('FIRST_ARR_DATE')->nullable();
            $table->dateTime('INSTALLMENT_DATE')->nullable();
            $table->decimal('PCPL_ORI', 20, 2)->nullable();
            $table->decimal('PAID_PRINCIPAL', 20, 2)->nullable();
            $table->decimal('PAID_INTEREST', 20, 2)->nullable();
            $table->decimal('PAID_PENALTY', 20, 2)->nullable();
            $table->decimal('DUE_PRINCIPAL', 20, 2)->nullable();
            $table->decimal('DUE_INTEREST', 20, 2)->nullable();
            $table->decimal('DUE_PENALTY', 20, 2)->nullable();
            $table->string('CREDIT_TYPE', 45)->nullable();
            $table->integer('INSTALLMENT_COUNT')->nullable();
            $table->integer('PERIOD', false, true)->nullable();
            $table->decimal('INSTALLMENT', 20, 2)->nullable();
            $table->decimal('FLAT_RATE', 6, 2)->nullable();
            $table->decimal('EFF_RATE', 6, 2)->nullable();
            $table->integer('VERSION')->nullable();
            $table->date('MOD_DATE')->nullable();
            $table->string('MOD_USER', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

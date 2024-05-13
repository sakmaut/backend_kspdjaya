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
        Schema::create('cr_application', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('BRANCH', 45)->nullable();
            $table->string('FORM_NUMBER', 45)->nullable();
            $table->string('ORDER_NUMBER', 45)->nullable();
            $table->string('CUST_CODE', 45)->nullable();
            $table->dateTime('ENTRY_DATE')->nullable();
            $table->decimal('SUBMISSION_VALUE', 20, 2)->nullable();
            $table->string('CREDIT_TYPE', 45)->nullable();
            $table->integer('INSTALLMENT_COUNT')->nullable();
            $table->integer('PERIOD')->nullable();
            $table->decimal('INSTALLMENT', 20, 2)->nullable();
            $table->decimal('RATE', 6, 2)->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY', 45)->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY', 45)->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_application');
    }
};

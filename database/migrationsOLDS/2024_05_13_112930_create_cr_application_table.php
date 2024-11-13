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
            $table->string('ID')->primary();
            $table->string('CR_SURVEY_ID')->nullable();
            $table->string('BRANCH')->nullable();
            $table->string('FORM_NUMBER')->nullable();
            $table->string('ORDER_NUMBER')->nullable();
            $table->string('CUST_CODE')->nullable();
            $table->dateTime('ENTRY_DATE')->nullable();
            $table->decimal('SUBMISSION_VALUE', 20, 2)->nullable();
            $table->string('CREDIT_TYPE')->nullable();
            $table->integer('INSTALLMENT_COUNT')->nullable();
            $table->integer('PERIOD')->nullable();
            $table->decimal('INSTALLMENT', 20, 2)->nullable();
            $table->string('OPT_PERIODE')->nullable();
            $table->decimal('FLAT_RATE', 6, 2)->nullable();
            $table->decimal('EFF_RATE', 6, 2)->nullable();
            $table->string('POKOK_PEMBAYARAN')->nullable();
            $table->string('NET_ADMIN')->nullable();
            $table->string('TOTAL_ADMIN')->nullable();
            $table->string('CADANGAN')->nullable();
            $table->string('PAYMENT_WAY')->nullable();
            $table->string('PROVISION')->nullable();
            $table->string('INSURANCE')->nullable();
            $table->string('TRANSFER_FEE')->nullable();
            $table->string('INTEREST_MARGIN')->nullable();
            $table->string('PRINCIPAL_MARGIN')->nullable();
            $table->string('LAST_INSTALLMENT')->nullable();
            $table->string('INTEREST_MARGIN_EFF_ACTUAL')->nullable();
            $table->string('INTEREST_MARGIN_EFF_FLAT')->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY')->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
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

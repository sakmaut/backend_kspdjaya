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
        Schema::create('arrears', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('STATUS_REC', 45)->nullable();
            $table->string('LOAN_NUMBER', 45)->nullable();
            $table->date('START_DATE')->nullable();
            $table->date('END_DATE')->nullable();
            $table->decimal('PAST_DUE_PCPL', 20, 2)->nullable();
            $table->decimal('PAST_DUE_INTRST', 20, 2)->nullable();
            $table->decimal('PAST_DUE_PENALTY', 20, 2)->nullable();
            $table->decimal('PAID_PCPL', 20, 2)->nullable();
            $table->decimal('PAID_INT', 20, 2)->nullable();
            $table->decimal('PAID_PENALTY', 20, 2)->nullable();
            $table->decimal('WOFF_PCPL', 20, 2)->nullable();
            $table->decimal('WOFF_INT', 20, 2)->nullable();
            $table->decimal('WOFF_PENALTY', 20, 2)->nullable();
            $table->decimal('PENALTY_RATE', 20, 2)->nullable();
            $table->string('TRNS_CODE', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arrears');
    }
};

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
        Schema::create('arrears_before', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('NO_INVOICE')->nullable();
            $table->string('STATUS_REC')->nullable();
            $table->string('LOAN_NUMBER');
            $table->date('START_DATE')->nullable();
            $table->date('END_DATE')->nullable();
            $table->decimal('PAST_DUE_PCPL', 15, 2)->default(0);
            $table->decimal('PAST_DUE_INTRST', 15, 2)->default(0);
            $table->decimal('PAST_DUE_PENALTY', 15, 2)->default(0);
            $table->decimal('PAID_PCPL', 15, 2)->default(0);
            $table->decimal('PAID_INT', 15, 2)->default(0);
            $table->decimal('PAID_PENALTY', 15, 2)->default(0);
            $table->decimal('WOFF_PCPL', 15, 2)->default(0);
            $table->decimal('WOFF_INT', 15, 2)->default(0);
            $table->decimal('WOFF_PENALTY', 15, 2)->default(0);
            $table->decimal('PENALTY_RATE', 5, 2)->default(0);
            $table->string('TRNS_CODE')->nullable();
            $table->timestamp('CREATED_AT')->nullable();
            $table->timestamp('UPDATED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arrears_before');
    }
};

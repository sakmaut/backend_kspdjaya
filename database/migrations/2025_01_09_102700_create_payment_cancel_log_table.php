<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_cancel_log', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('INVOICE_NUMBER')->nullable();
            $table->string('REQUEST_BY')->nullable();
            $table->string('REQUEST_BRANCH')->nullable();
            $table->string('REQUEST_POSITION')->nullable();
            $table->string('REQUEST_DESCR')->nullable();
            $table->dateTime('REQUEST_DATE')->nullable(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('ONCHARGE_PERSON')->nullable();
            $table->dateTime('ONCHARGE_TIME')->nullable();
            $table->string('ONCHARGE_DESCR')->nullable();
            $table->string('ONCHARGE_FLAG')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_cancel_log');
    }
};

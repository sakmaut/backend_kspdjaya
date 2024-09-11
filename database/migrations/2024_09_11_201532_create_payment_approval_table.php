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
        Schema::create('payment_approval', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('PAYMENT_ID')->nullable();
            $table->string('ONCHARGE_APPRVL')->nullable();
            $table->string('ONCHARGE_PERSON')->nullable();
            $table->dateTime('ONCHARGE_TIME')->nullable(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('ONCHARGE_DESCR')->nullable();
            $table->string('APPROVAL_RESULT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_approval');
    }
};

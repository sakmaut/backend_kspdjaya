<?php

use Carbon\Carbon;
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
        Schema::create('customer_phone', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CUSTOMER_ID')->nullable();
            $table->string('ALIAS')->nullable();
            $table->string('PHONE_NUMBER')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->dateTime('CREATED_AT')->nullable()->default(Carbon::now());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_phone');
    }
};

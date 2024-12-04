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
        Schema::create('customer_document', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CUSTOMER_ID')->nullable();
            $table->string('TYPE')->nullable();
            $table->string('COUNTER_ID')->nullable();
            $table->string('PATH')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_document');
    }
};

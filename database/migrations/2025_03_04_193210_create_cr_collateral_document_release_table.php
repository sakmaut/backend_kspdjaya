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
        Schema::create('cr_collateral_document_release', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('COLLATERAL_ID')->nullable();
            $table->string('TYPE')->nullable();
            $table->string('COUNTER_ID')->nullable();
            $table->string('PATH')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->dateTime('CREATED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_collateral_document_release');
    }
};

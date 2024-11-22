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
        Schema::create('location_status', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('COLLATERAL_ID')->nullable();
            $table->string('TYPE')->nullable();
            $table->string('LOCATION')->nullable();
            $table->string('CREATE_BY')->nullable();
            $table->timestamp('CREATED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_status');
    }
};

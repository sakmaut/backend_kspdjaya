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
        Schema::create('credit', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('LOAN_NUMBER')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->nullable()->useCurrent(); 
            $table->string('DELETED_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit');
    }
};

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
        Schema::create('cr_application_bank', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('APPLICATION_ID')->nullable();
            $table->string('BANK_CODE')->nullable();
            $table->string('BANK_NAME')->nullable();
            $table->string('ACCOUNT_NUMBER')->nullable();
            $table->string('ACCOUNT_NAME')->nullable();
            $table->string('PREFERENCE_FLAG')->nullable();
            $table->string('STATUS')->nullable();
    
            $table->foreign('APPLICATION_ID')->references('ID')->on('cr_application')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_application_bank');
    }
};

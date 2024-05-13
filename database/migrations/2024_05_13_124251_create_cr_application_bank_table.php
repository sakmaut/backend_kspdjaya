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
            $table->string('ID', 100)->primary();
            $table->string('APPLICATION_ID', 100)->nullable();
            $table->string('BANK_CODE', 45)->nullable();
            $table->string('BANK_NAME', 45)->nullable();
            $table->string('ACCOUNT_NUMBER', 45)->nullable();
            $table->string('ACCOUNT_NAME', 100)->nullable();
            $table->string('PREFERENCE_FLAG', 45)->nullable();
            $table->string('STATUS', 45)->nullable();
    
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

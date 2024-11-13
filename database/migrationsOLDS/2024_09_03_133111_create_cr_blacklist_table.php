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
        Schema::create('cr_blacklist', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('LOAN_NUMBER', 45)->nullable();
            $table->string('KTP', 45)->nullable();
            $table->string('KK', 45)->nullable();
            $table->string('COLLATERAL', 100)->nullable();
            $table->string('RES_1', 45)->nullable();
            $table->string('RES_2', 45)->nullable();
            $table->string('PERSON', 100)->nullable();
            $table->dateTime('DATE_ADD')->nullable();
            $table->string('NOTE', 450)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_blacklist');
    }
};

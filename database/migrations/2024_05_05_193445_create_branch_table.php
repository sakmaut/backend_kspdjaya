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
        Schema::create('branch', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CODE', 45)->unique()->nullable();
            $table->string('NAME', 45)->nullable();
            $table->string('ADDRESS', 2000)->nullable();
            $table->string('LOCATION', 2000)->nullable();
            $table->string('PHONE_1', 45)->nullable();
            $table->string('PHONE_2', 45)->nullable();
            $table->string('PHONE_3', 45)->nullable();
            $table->string('DESCR', 45)->nullable();
            $table->string('STATUS', 45)->nullable();
            $table->date('CREATE_DATE')->nullable();
            $table->string('CREATE_USER', 45)->nullable();
            $table->date('MOD_DATE')->nullable();
            $table->string('MOD_USER', 45)->nullable();
            $table->integer('VERSION')->nullable();
            $table->string('DELETED_BY', 10)->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch');
    }
};

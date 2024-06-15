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
            $table->string('CODE')->unique()->nullable();
            $table->string('NAME')->nullable();
            $table->string('ADDRESS')->nullable();
            $table->string('RT')->nullable();
            $table->string('RW')->nullable();
            $table->string('PROVINCE')->nullable();
            $table->string('CITY')->nullable();
            $table->string('KELURAHAN')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('ZIP_CODE')->nullable();
            $table->string('LOCATION')->nullable();
            $table->string('PHONE_1')->nullable();
            $table->string('PHONE_2')->nullable();
            $table->string('PHONE_3')->nullable();
            $table->string('DESCR')->nullable();
            $table->string('STATUS')->nullable();
            $table->date('CREATE_DATE')->nullable();
            $table->string('CREATE_USER')->nullable();
            $table->date('MOD_DATE')->nullable();
            $table->string('MOD_USER')->nullable();
            $table->integer('VERSION')->nullable();
            $table->string('DELETED_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
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

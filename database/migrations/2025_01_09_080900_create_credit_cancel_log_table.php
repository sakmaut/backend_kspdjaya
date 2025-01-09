<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_cancel_log', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CREDIT_ID')->nullable();
            $table->string('ONCHARGE_DESCR')->nullable();
            $table->string('ONCHARGE_PERSON')->nullable();
            $table->dateTime('ONCHARGE_TIME')->nullable(DB::raw('CURRENT_TIMESTAMP'));
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('credit_cancel_log');
    }
};

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
        Schema::create('prospect_approval', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CR_PROSPECT_ID', 100)->nullable();
            $table->string('ONCHARGE_APPRVL', 10)->nullable();
            $table->string('ONCHARGE_PERSON', 100)->nullable();
            $table->dateTime('ONCHARGE_TIME')->nullable();
            $table->string('ONCHARGE_DESCR', 450)->nullable();
            $table->string('APPROVAL_RESULT', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slik_approval');
    }
};

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
        Schema::create('saving_log', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('SAVING_ID', 45)->nullable();
            $table->string('TRX_TYPE', 45)->nullable();
            $table->datetime('TRX_DATE')->nullable();
            $table->decimal('BALANCE', 20, 2)->nullable();
            $table->string('DESCRIPTION', 450)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_log');
    }
};

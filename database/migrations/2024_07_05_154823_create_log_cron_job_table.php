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
        Schema::create('log_cron_job', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('status', 20)->nullable();
            $table->string('jumlah_data', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->dateTime('created_at')->useCurrent()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_cron_job');
    }
};

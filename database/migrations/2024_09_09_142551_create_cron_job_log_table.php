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
        Schema::create('cron_job_log', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('STATUS', 100)->nullable();
            $table->text('DESCRIPTION')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_log');
    }
};

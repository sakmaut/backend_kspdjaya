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
        Schema::create('log_activity_login', function (Blueprint $table) {
            $table->string('id', 45)->primary();
            $table->string('user', 50)->nullable();
            $table->string('event', 20);
            $table->integer('status');
            $table->string('url_api', 255);
            $table->longText('activity_description')->nullable();
            $table->string('device_info', 255)->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->string('browser', 255)->nullable();
            $table->timestamp('time')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_activity_login');
    }
};

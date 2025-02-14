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
        Schema::create('telegram_bot_send', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('endpoint')->nullable();
            $table->string('messages')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('created_at')->nullable(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_send');
    }
};

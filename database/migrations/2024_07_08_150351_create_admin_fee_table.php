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
        Schema::create('admin_fee', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('branch', 45)->nullable();
            $table->decimal('start_value', 20, 2)->nullable();
            $table->decimal('end_value', 20, 2)->nullable();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_fee');
    }
};

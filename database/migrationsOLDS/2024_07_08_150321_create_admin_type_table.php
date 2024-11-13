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
        Schema::create('admin_type', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('admin_fee_id', 100)->nullable();
            $table->string('fee_name', 450)->nullable();
            $table->decimal('6_month', 20, 2)->nullable();
            $table->decimal('12_month', 20, 2)->nullable();
            $table->decimal('18_month', 20, 2)->nullable();
            $table->decimal('24_month', 20, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_type');
    }
};

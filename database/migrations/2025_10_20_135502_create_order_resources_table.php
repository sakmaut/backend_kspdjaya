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
        Schema::create('order_resources', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('KODE')->nullable();
            $table->string('NAMA')->nullable();
            $table->string('NO_HP')->nullable();
            $table->text('KETERANGAN')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
            $table->string('DELETED_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_resources');
    }
};

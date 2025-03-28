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
        Schema::create('taksasi', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('brand')->nullable();
            $table->string('code')->nullable();
            $table->string('model')->nullable();
            $table->string('descr')->nullable();
            $table->timestamp('create_at')->nullable();
            $table->string('create_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taksasi');
    }
};

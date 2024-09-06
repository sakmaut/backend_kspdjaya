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
        Schema::create('bpkb_transaction', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('FROM_BRANCH', 100)->nullable();
            $table->string('TO_BRANCH', 100)->nullable();
            $table->string('CATEGORY', 45)->nullable();
            $table->text('NOTE')->nullable();
            $table->string('STATUS', 100)->nullable();
            $table->string('CREATED_BY', 20)->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bpkb_transaction');
    }
};

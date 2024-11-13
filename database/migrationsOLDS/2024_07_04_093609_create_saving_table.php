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
        Schema::create('saving', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CUST_CODE', 45)->nullable();
            $table->string('ACC_NUM', 45)->nullable();
            $table->decimal('BALANCE', 20, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving');
    }
};

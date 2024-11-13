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
        Schema::create('taksasi_price', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('taksasi_id')->nullable();
            $table->string('year')->nullable();
            $table->decimal('price',20,2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taksasi_price');
    }
};

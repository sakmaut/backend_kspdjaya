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
        Schema::create('taksasi_bak', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('brand')->nullable();
            $table->string('code')->nullable();
            $table->string('model')->nullable();
            $table->string('descr')->nullable();
            $table->string('year')->nullable();
            $table->decimal('price',20,2)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taksasi_bak');
    }
};

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
        Schema::create('cr_application_fee', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('APPLICATION_ID')->nullable();
            $table->string('FEE_NAME')->nullable();
            $table->decimal('FEE_PCT', 6, 2)->nullable();
            $table->decimal('FEE_VALUE', 20, 2)->nullable();

            $table->foreign('APPLICATION_ID')->references('ID')->on('cr_application')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_application_fee');
    }
};

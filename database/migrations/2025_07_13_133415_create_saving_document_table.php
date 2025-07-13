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
        Schema::create('saving_document', function (Blueprint $table) {
            $table->string('ID')->nullable(false);
            $table->string('TYPE')->nullable();
            $table->string('PATH')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_document');
    }
};

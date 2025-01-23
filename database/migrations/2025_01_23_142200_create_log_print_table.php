<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('log_print', function (Blueprint $table) {
            $table->string('ID')->nullable();
            $table->integer('COUNT')->default(0);
            $table->string('PRINT_BRANCH')->nullable();
            $table->string('PRINT_POSITION')->nullable();
            $table->string('PRINT_BY')->nullable();
            $table->dateTime('PRINT_DATE')->nullable(DB::raw('CURRENT_TIMESTAMP'));
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_print');
    }
};

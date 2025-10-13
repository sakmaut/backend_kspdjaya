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
        Schema::create('cl_lkp', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('LKP_NUMBER')->nullable(false);
            $table->string('BRANCH_ID')->nullable();
            $table->integer('NOA')->nullable();
            $table->integer('TOTAL_ANGSURAN')->nullable();
            $table->string('STATUS')->nullable();
            $table->string('STATUS_EXP')->nullable();
            $table->string('CREATED_BY')->nullable();
            $table->timestamp('CREATED_AT')->useCurrent();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('UPDATED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_lkp');
    }
};

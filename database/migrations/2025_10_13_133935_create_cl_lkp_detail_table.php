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
        Schema::create('cl_lkp_detail', function (Blueprint $table) {
            $table->uuid('ID')->primary();
            $table->string('LKP_ID')->nullable(false);
            $table->string('LOAN_NUMBER')->nullable();
            $table->string('LOAN_HOLDER')->nullable();
            $table->text('ADDRESS')->nullable();
            $table->date('DUE_DATE')->nullable();
            $table->string('CYCLE')->nullable();
            $table->integer('INST_COUNT')->nullable();
            $table->string('PRINCIPAL')->nullable();
            $table->string('INTEREST')->nullable();
            $table->string('PINALTY')->nullable();
            $table->timestamp('VISIT_TIME')->nullable();
            $table->text('VISIT_RESULT')->nullable();
            $table->timestamp('EVAL_DATE')->nullable();
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
        Schema::dropIfExists('cl_lkp_detail');
    }
};

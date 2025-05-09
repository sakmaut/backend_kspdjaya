<?php

use Carbon\Carbon;
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
        Schema::create('cr_collateral_request', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('COLLATERAL_ID')->nullable();
            $table->string('ON_BEHALF')->nullable();
            $table->string('POLICE_NUMBER')->nullable();
            $table->string('CHASIS_NUMBER')->nullable();
            $table->string('ENGINE_NUMBER')->nullable();
            $table->string('BPKB_NUMBER')->nullable();
            $table->string('BPKB_ADDRESS')->nullable();
            $table->string('STNK_NUMBER')->nullable();
            $table->string('INVOICE_NUMBER')->nullable();
            $table->date('STNK_VALID_DATE')->nullable();
            $table->integer('STATUS')->default(0);
            $table->string('APPROVED_BY')->nullable();
            $table->string('APPROVED_POSITION')->nullable();
            $table->dateTime('APPROVED_AT')->nullable();
            $table->string('REQUEST_BY')->nullable();
            $table->string('REQUEST_BRANCH')->nullable();
            $table->string('REQUEST_POSITION')->nullable();
            $table->dateTime('REQUEST_AT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_collateral_request');
    }
};

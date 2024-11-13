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
        Schema::create('cr_collateral', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CR_CREDIT_ID')->nullable(false);
            $table->string('HEADER_ID')->nullable();
            $table->string('BRAND')->nullable();
            $table->string('TYPE')->nullable();
            $table->string('PRODUCTION_YEAR')->nullable();
            $table->string('COLOR')->nullable();
            $table->string('ON_BEHALF')->nullable();
            $table->string('POLICE_NUMBER')->nullable();
            $table->string('CHASIS_NUMBER')->nullable();
            $table->string('ENGINE_NUMBER')->nullable();
            $table->string('BPKB_NUMBER')->nullable();
            $table->string('STNK_NUMBER')->nullable();
            $table->decimal('VALUE', 20, 2)->nullable();
            $table->string('COLLATERAL_FLAG')->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY')->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY')->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_collateral');
    }
};

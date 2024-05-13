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
        Schema::create('cr_guarante_vehicle', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('CR_PROSPECT_ID', 100)->nullable(false);
            $table->string('HEADER_ID', 100)->nullable();
            $table->string('BRAND', 45)->nullable();
            $table->string('TYPE', 450)->nullable();
            $table->string('PRODUCTION_YEAR', 45)->nullable();
            $table->string('COLOR', 45)->nullable();
            $table->string('ON_BEHALF', 200)->nullable();
            $table->string('POLICE_NUMBER', 45)->nullable();
            $table->string('CHASIS_NUMBER', 45)->nullable();
            $table->string('ENGINE_NUMBER', 45)->nullable();
            $table->string('BPKB_NUMBER', 45)->nullable();
            $table->decimal('VALUE', 20, 2)->nullable();
            $table->string('COLLATERAL_FLAG', 45)->nullable();
            $table->integer('VERSION')->nullable();
            $table->timestamp('CREATE_DATE')->nullable();
            $table->string('CREATE_BY', 45)->nullable();
            $table->timestamp('MOD_DATE')->nullable();
            $table->string('MOD_BY', 45)->nullable();
            $table->timestamp('DELETED_AT')->nullable();
            $table->string('DELETED_BY', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guarante_vehicle');
    }
};

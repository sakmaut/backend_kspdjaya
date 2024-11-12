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
        Schema::create('cr_collateral_sertification', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CR_CREDIT_ID')->nullable(false);
            $table->string('STATUS_JAMINAN')->nullable();
            $table->string('NO_SERTIFIKAT')->nullable();
            $table->string('STATUS_KEPEMILIKAN')->nullable();
            $table->string('IMB')->nullable();
            $table->string('LUAS_TANAH')->nullable();
            $table->string('LUAS_BANGUNAN')->nullable();
            $table->string('LOKASI')->nullable();
            $table->string('PROVINSI')->nullable();
            $table->string('KAB_KOTA')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('DESA')->nullable();
            $table->string('ATAS_NAMA')->nullable();
            $table->decimal('NILAI', 20, 2)->nullable();
            $table->string('LOCATION')->nullable();
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
        Schema::dropIfExists('cr_collateral_sertification');
    }
};

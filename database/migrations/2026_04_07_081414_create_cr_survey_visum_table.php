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
        Schema::create('cr_survey_visum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_konsumen');
            $table->text('alamat_konsumen')->nullable();
            $table->string('no_handphone', 20)->nullable();
            $table->string('status_konsumen')->nullable();
            $table->string('hasil_followup')->nullable();
            $table->string('sumber_order')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('path')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_survey_visum');
    }
};

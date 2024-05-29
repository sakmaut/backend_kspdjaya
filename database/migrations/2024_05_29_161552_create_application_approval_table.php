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
        Schema::create('application_approval', function (Blueprint $table) {
            $table->string('ID', 100)->primary();
            $table->string('cr_prospect_id', 100)->nullable();
            $table->string('cr_application_id', 100)->nullable();
            $table->string('cr_prospect_kapos', 45)->nullable();
            $table->dateTime('cr_prospect_kapos_time')->nullable();
            $table->string('cr_prospect_kapos_note', 450)->nullable();
            $table->string('cr_application_kapos', 45)->nullable();
            $table->dateTime('cr_application_kapos_time')->nullable();
            $table->string('cr_application_kapos_note', 450)->nullable();
            $table->string('cr_application_ho', 45)->nullable();
            $table->dateTime('cr_application_ho_time')->nullable();
            $table->string('cr_application_ho_note', 450)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_approval');
    }
};

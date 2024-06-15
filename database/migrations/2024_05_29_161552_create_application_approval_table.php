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
            $table->string('id')->primary();
            $table->string('cr_prospect_id')->nullable();
            $table->string('cr_application_id')->nullable();
            $table->string('cr_prospect_kapos')->nullable();
            $table->dateTime('cr_prospect_kapos_time')->nullable();
            $table->string('cr_prospect_kapos_note')->nullable();
            $table->string('cr_application_kapos')->nullable();
            $table->dateTime('cr_application_kapos_time')->nullable();
            $table->string('cr_application_kapos_note')->nullable();
            $table->string('cr_application_kapos_desc')->nullable();
            $table->string('cr_application_ho')->nullable();
            $table->dateTime('cr_application_ho_time')->nullable();
            $table->string('cr_application_ho_note')->nullable();
            $table->string('cr_application_ho_desc')->nullable();
            $table->string('application_result');
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

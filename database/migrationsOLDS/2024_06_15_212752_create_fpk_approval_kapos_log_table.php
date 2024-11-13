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
        Schema::create('fpk_approval_kapos_log', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('application_approval_id')->nullable(false);
            $table->string('cr_application_kapos')->nullable();
            $table->dateTime('cr_application_kapos_time')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('cr_application_kapos_note')->nullable();
            $table->string('cr_application_kapos_desc')->nullable();
            $table->string('application_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fpk_approval_kapos_log');
    }
};

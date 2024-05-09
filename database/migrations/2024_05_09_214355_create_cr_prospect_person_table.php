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
        Schema::create('cr_prospect_person', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('cr_prospect_id', 100);
            $table->string('nama', 255)->nullable();
            $table->string('ktp', 20)->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->string('pekerjaan', 255)->nullable();
            $table->string('status', 25)->nullable();
            
            $table->foreign('cr_prospect_id')->references('id')->on('cr_prospect')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cr_prospect_person');
    }
};

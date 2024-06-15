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
        Schema::dropIfExists('jabatan_access_menu');
        Schema::create('jabatan_access_menu', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('jabatan')->nullable();
            $table->string('master_menu_id')->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jabatan_access_menu');
    }
};

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
        Schema::table('cl_lkp_detail', function (Blueprint $table) {
            $table->string('NO_SURAT')->nullable()->after('LKP_ID');
            $table->string('DESA')->nullable()->after('ADDRESS');
            $table->string('KEC')->nullable()->after('DESA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

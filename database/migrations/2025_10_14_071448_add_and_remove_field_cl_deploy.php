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
        Schema::table('cl_deploy', function (Blueprint $table) {
            $table->dropColumn('TGL_KUNJUNGAN');
            $table->dropColumn('KETERANGAN');
            // Tambah field
            $table->string('NO_SURAT')->nullable()->after('ID');
            $table->string('MCF')->nullable()->after('KEC');
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

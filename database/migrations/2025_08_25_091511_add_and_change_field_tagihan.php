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
        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('NO_SURAT', 50)->nullable()->after('USER_ID');
            $table->date('TGL_EXP')->nullable()->after('ALAMAT');
            $table->date('TGL_KUNJUNGAN')->nullable()->after('TGL_EXP');
            $table->text('KETERANGAN')->nullable()->after('TGL_KUNJUNGAN');
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

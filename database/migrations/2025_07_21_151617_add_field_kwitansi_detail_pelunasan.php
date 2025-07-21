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
        Schema::table('kwitansi_detail_pelunasan', function (Blueprint $table) {
            $table->decimal('principal', 15, 2)->nullable()->after('tgl_angsuran');
            $table->decimal('interest', 15, 2)->nullable()->after('principal');
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

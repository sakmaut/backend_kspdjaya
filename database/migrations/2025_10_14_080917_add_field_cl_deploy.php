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
            $table->integer('ANGSURAN_KE')->nullable()->after('MCF');
            $table->integer('ANGSURAN')->nullable()->after('ANGSURAN_KE');
            $table->integer('BAYAR')->nullable()->after('ANGSURAN');
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

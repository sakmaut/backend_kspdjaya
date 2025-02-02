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
        Schema::table('kwitansi', function (Blueprint $table) {
            $table->decimal('PINALTY_PELUNASAN',25,2)->after('TOTAL_BAYAR')->default(0);
            $table->decimal('DISKON_PINALTY_PELUNASAN', 25, 2)->after('PINALTY_PELUNASAN')->default(0);
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

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
        Schema::table('cr_application', function (Blueprint $table) {
            $table->string('NET_ADMIN')->after('POKOK_PEMBAYARAN')->nullable();
            $table->string('TOTAL_ADMIN')->after('NET_ADMIN')->nullable();
            $table->string('CADANGAN')->after('TOTAL_ADMIN')->nullable();
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

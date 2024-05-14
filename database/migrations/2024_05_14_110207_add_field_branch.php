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
        Schema::table('branch', function (Blueprint $table) {
            $table->string('RT')->nullable()->after('ADDRESS');
            $table->string('RW')->nullable()->after('RT');
            $table->string('PROVINCE')->nullable()->after('RW');
            $table->string('CITY')->nullable()->after('PROVINCE');
            $table->string('KELURAHAN')->nullable()->after('CITY');
            $table->string('KECAMATAN')->nullable()->after('KELURAHAN');
            $table->string('ZIP_CODE')->nullable()->after('KECAMATAN');
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

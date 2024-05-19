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
        Schema::table('cr_prospect', function (Blueprint $table) {
            $table->string('rt')->nullable()->after('alamat');
            $table->string('rw')->nullable()->after('rt');
            $table->string('province')->nullable()->after('rw');
            $table->string('city')->nullable()->after('province');
            $table->string('kelurahan')->nullable()->after('city');
            $table->string('kecamatan')->nullable()->after('kelurahan');
            $table->string('zip_code')->nullable()->after('kecamatan');
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

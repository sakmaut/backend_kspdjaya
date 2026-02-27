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
        Schema::table('cr_blacklist', function (Blueprint $table) {
            $table->string('STATUS')->nullable()->after('RES_2');
            $table->string('UPDATED_BY')->nullable()->after('DATE_ADD');
            $table->timestamp('UPDATED_AT')->nullable()->after('UPDATED_BY');
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

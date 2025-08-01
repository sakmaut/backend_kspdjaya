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
        Schema::table('credit', function (Blueprint $table) {
            $table->string('SITA_BY')->nullable();
            $table->timestamp('SITA_AT')->nullable()->after('SITA_BY');
            $table->string('JUAL_BY')->nullable()->after('SITA_AT');
            $table->timestamp('JUAL_AT')->nullable()->after('JUAL_BY');
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

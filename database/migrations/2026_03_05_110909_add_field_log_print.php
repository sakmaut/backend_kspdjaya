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
        Schema::table('log_print', function (Blueprint $table) {
            $table->string('RESETTER_BY')->nullable()->after('PRINT_DATE');
            $table->timestamp('RESETTER_AT')->nullable()->after('RESETTER_BY');
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

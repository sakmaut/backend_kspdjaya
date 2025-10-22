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
            $table->string('TENOR')->nullable()->after('N_BOT');
            $table->text('CATT_SURVEY')->nullable()->after('TENOR');
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

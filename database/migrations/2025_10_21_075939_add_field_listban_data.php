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
        Schema::table('listban_data', function (Blueprint $table) {
            $table->string('BRANCH_ID')->nullable()->after('ID');
            $table->string('CREDIT_ID')->nullable()->after('NAMA_CABANG');
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

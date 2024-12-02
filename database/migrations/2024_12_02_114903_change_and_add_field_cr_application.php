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
            $table->decimal('EFF_RATE',25,10)->nullable()->change();
            $table->decimal('FLAT_RATE', 25, 10)->nullable()->change();
            $table->decimal('INTEREST_RATE', 25, 10)->nullable()->after('EFF_RATE');
            $table->decimal('TOTAL_INTEREST', 25, 10)->nullable()->after('INTEREST_RATE'); 
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

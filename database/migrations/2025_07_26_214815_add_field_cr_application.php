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
            $table->decimal('INTEREST_FEE', 15, 2)->default(0)->after('TOTAL_ADMIN');
            $table->decimal('PROCCESS_FEE', 15, 2)->default(0)->after('INTEREST_FEE');
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

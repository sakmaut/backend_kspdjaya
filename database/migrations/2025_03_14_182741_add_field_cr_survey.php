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
        Schema::table('cr_survey', function (Blueprint $table) {
            $table->decimal('interest_month',6,2)->after('tenor')->nullable()->default(0);
            $table->decimal('interest_year', 6, 2)->after('interest_month')->nullable()->default(0);
            $table->decimal('installment', 25, 2)->after('interest_year')->nullable()->default(0);
            $table->string('collateral_type')->after('installment')->nullable();
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

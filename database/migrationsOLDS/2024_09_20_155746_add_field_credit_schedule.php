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
        Schema::table('credit_schedule', function (Blueprint $table) {
            $table->double('PAYMENT_VALUE_PRINCIPAL')->nullable()->after('PRINCIPAL_REMAINS');
            $table->double('PAYMENT_VALUE_INTEREST')->nullable()->after('PAYMENT_VALUE_PRINCIPAL');
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

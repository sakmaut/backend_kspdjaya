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
            $table->decimal('DISCOUNT_PRINCIPAL', 25, 2)->after('DUE_PENALTY')->default(0);
            $table->decimal('DISCOUNT_INTEREST', 25, 2)->after('DISCOUNT_PRINCIPAL')->default(0);
            $table->decimal('DISCOUNT_PENALTY', 25, 2)->after('DISCOUNT_INTEREST')->default(0);
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

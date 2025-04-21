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
        Schema::table('kwitansi_structur_detail', function (Blueprint $table) {
            $table->decimal('principal_prev', 25, 2)->after('payment')->nullable();
            $table->decimal('interest_prev', 25, 2)->after('principal_prev')->nullable();
            $table->decimal('insuficient_payment_prev', 25, 2)->after('interest_prev')->nullable();
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

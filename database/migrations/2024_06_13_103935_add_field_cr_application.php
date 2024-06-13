<?php

use Illuminate\Contracts\View\View;
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
            $table->string('PAYMENT_WAY')->after('EFF_RATE')->nullable();
            $table->string('PROVISION')->after('PAYMENT_WAY')->nullable();
            $table->string('INSURANCE')->after('PROVISION')->nullable();
            $table->string('TRANSFER_FEE')->after('INSURANCE')->nullable();
            $table->string('INTEREST_MARGIN')->after('TRANSFER_FEE')->nullable();
            $table->string('PRINCIPAL_MARGIN')->after('INTEREST_MARGIN')->nullable();
            $table->string('LAST_INSTALLMENT')->after('PRINCIPAL_MARGIN')->nullable();
            $table->string('INTEREST_MARGIN_EFF_ACTUAL')->after('LAST_INSTALLMENT')->nullable();
            $table->string('INTEREST_MARGIN_EFF_FLAT')->after('INTEREST_MARGIN_EFF_ACTUAL')->nullable();
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

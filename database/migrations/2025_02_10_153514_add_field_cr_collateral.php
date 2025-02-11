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
        Schema::table('cr_collateral', function (Blueprint $table) {
            $table->string('BPKB_ADDRESS')->after('BPKB_NUMBER');
            $table->string('INVOICE_NUMBER')->after('STNK_NUMBER');
            $table->date('STNK_VALID_DATE')->after('INVOICE_NUMBER')->nullable();
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

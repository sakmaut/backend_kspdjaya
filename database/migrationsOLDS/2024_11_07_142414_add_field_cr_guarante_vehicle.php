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
        Schema::table('cr_guarante_vehicle', function (Blueprint $table) {
            $table->text('BPKB_ADDRESS')->nullable()->after('BPKB_NUMBER');
            $table->string('INVOICE_NUMBER')->nullable()->after('STNK_NUMBER');
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

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
            $table->string('INSTALLMENT_TYPE', 255)->nullable()->after('EFF_RATE');
            $table->string('TENOR', 45)->nullable()->after('INSTALLMENT_TYPE');
            $table->double('ACC_VALUE', 25, 2)->nullable()->after('TENOR');
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

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
        Schema::table('payment', function (Blueprint $table) {
            $table->string('INVOICE', 45)->nullable()->after('STTS_RCRD');
            $table->string('ACC_NUM', 45)->nullable()->after('INVOICE');
            $table->string('ARREARS_ID', 100)->nullable()->after('AUTH_DATE');
            $table->string('ATTACHMENT')->nullable()->after('ARREARS_ID');
            $table->string('BANK_NAME')->nullable()->after('ATTACHMENT');
            $table->string('BANK_ACC_NUMBER')->nullable()->after('BANK_NAME');
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

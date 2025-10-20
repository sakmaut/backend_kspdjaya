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
        Schema::table('cl_deploy', function (Blueprint $table) {
            $table->dropColumn('NAMA_CUST');
            $table->dropColumn('ALAMAT');
            $table->dropColumn('DESA');
            $table->dropColumn('KEC');
            $table->dropColumn('BAYAR');

            $table->string('CUST_CODE')->nullable()->after('LOAN_NUMBER');
            $table->string('CREDIT_ID')->nullable()->after('BRANCH_ID');
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

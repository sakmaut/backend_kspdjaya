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
        // Check if index 'idx_customer_id_number' exists and drop it
        if (Schema::hasTable('customer') && Schema::hasIndex('customer', 'idx_customer_id_number')) {
            Schema::table('customer', function (Blueprint $table) {
                $table->dropIndex('idx_customer_id_number');
            });
        }

        // Adding index on ID_NUMBER column in customer table
        Schema::table('customer', function (Blueprint $table) {
            $table->index('ID_NUMBER', 'idx_customer_id_number');
        });

        // Adding index on CUST_CODE column in credit table
        Schema::table('credit', function (Blueprint $table) {
            // Check and drop existing index 'idx_credit_cust_code' if it exists
            if (Schema::hasIndex('credit', 'idx_credit_cust_code')) {
                $table->dropIndex('idx_credit_cust_code');
            }
            $table->index('CUST_CODE', 'idx_credit_cust_code');

            // Check and drop existing index 'idx_credit_created_at' if it exists
            if (Schema::hasIndex('credit', 'idx_credit_created_at')) {
                $table->dropIndex('idx_credit_created_at');
            }
            $table->index('CREATED_AT', 'idx_credit_created_at');
        });

        // Adding index on CR_CREDIT_ID column in cr_collateral table
        Schema::table('cr_collateral', function (Blueprint $table) {
            // Check and drop existing index 'idx_cr_collateral_credit_id' if it exists
            if (Schema::hasIndex('cr_collateral', 'idx_cr_collateral_credit_id')) {
                $table->dropIndex('idx_cr_collateral_credit_id');
            }
            $table->index('CR_CREDIT_ID', 'idx_cr_collateral_credit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indexing');
    }
};

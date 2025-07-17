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
        Schema::create('saving_transactions_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('acc_number', 20);
            $table->string('transaction_type');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_transaction');
    }
};

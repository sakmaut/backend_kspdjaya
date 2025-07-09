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
        Schema::create('sv_master_account', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stat_rec')->nullable();
            $table->string('acc_number')->nullable();
            $table->string('acc_name')->nullable();
            $table->string('cust_code')->nullable();
            $table->string('branch')->nullable();
            $table->string('acc_type')->nullable();
            $table->double('clear_bal')->nullable()->default(0);
            $table->double('min_bal')->nullable()->default(0);
            $table->date('date_last_trans')->nullable();
            $table->date('date_acc_open')->nullable();
            $table->date('date_acc_close')->nullable();
            $table->double('block_bal')->nullable()->default(0);
            $table->double('plafond_amount')->nullable()->default(0);
            $table->integer('version')->nullable()->default(1);
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable(false)->useCurrent();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sv_master_account');
    }
};

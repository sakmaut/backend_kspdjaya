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
        Schema::create('deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 50)->nullable();
            $table->string('deposit_number', 100)->unique();
            $table->string('deposit_holder', 255);
            $table->string('deposit_code', 50)->nullable();
            $table->string('branch', 50);
            $table->string('cust_code', 50);
            $table->integer('period')->default(0);
            $table->integer('term')->default(0);
            $table->string('roll_over')->nullable();
            $table->decimal('int_rate', 5, 2);
            $table->timestamp('entry_date')->nullable();
            $table->timestamp('ro_date')->nullable();
            $table->timestamp('mature_date')->nullable();
            $table->timestamp('pcpl_due_date')->nullable();
            $table->timestamp('int_due_date')->nullable();
            $table->integer('calc_day')->nullable();
            $table->decimal('deposit_value', 25, 2);
            $table->integer('flag_tax')->default(0);
            $table->decimal('accu_tax_val', 25, 2)->default(0);
            $table->decimal('accu_int_val', 25, 2)->default(0);
            $table->string('acc_source', 50)->nullable();
            $table->string('acc_destination', 50)->nullable();
            $table->text('descr')->nullable();
            $table->integer('print_count')->default(0);
            $table->string('print_by')->nullable();
            $table->timestamp('print_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};

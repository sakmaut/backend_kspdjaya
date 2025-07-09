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
        Schema::create('sv_master_product_saving', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_type')->nullable();
            $table->double('interest_rate')->nullable()->default(0);
            $table->double('min_deposit')->nullable()->default(0);
            $table->double('admin_fee')->nullable()->default(0);
            $table->integer('term_length')->nullable()->default(0);
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
        Schema::dropIfExists('sv_master_product_saving');
    }
};

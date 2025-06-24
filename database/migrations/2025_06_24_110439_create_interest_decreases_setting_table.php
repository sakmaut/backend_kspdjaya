<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interest_decreases_setting', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('tenor')->nullable();
            $table->double('interest')->nullable(false)->default(0);
            $table->double('admin_fee')->nullable(false)->default(0);
            $table->string('created_by')->nullable();
            $table->timestamp('created_at', 0)->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at', 0)->nullable()->default(null);;
            $table->string('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_decreases_setting');
    }
};

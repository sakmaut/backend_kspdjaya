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
        Schema::create('master_menu', function (Blueprint $table) {
            $table->string('id', 45)->primary();
            $table->string('menu_name')->nullable();
            $table->string('route', 100)->nullable();
            $table->string('parent', 45)->nullable();
            $table->integer('order')->nullable();
            $table->string('leading')->nullable();
            $table->string('action')->nullable();
            $table->string('status', 25)->nullable();
            $table->string('ability')->nullable();
            $table->string('created_by', 45)->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('updated_by', 45)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 45)->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_menu');
    }
};
